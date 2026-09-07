<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Federation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Service\Federation\FederatedAuthenticationService;
use Auth\Domain\Exception\Federation\{FederatedAuthException, FederatedConflictException, FederatedUnauthorizedException};
use Auth\Domain\ValueObject\Federation\FederatedProvider;
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Api\Dto\Input\Federation\FederatedCompleteInput;
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use Auth\Presentation\Api\Dto\Output\Federation\FederatedConnectionsOutput;
use Auth\Presentation\Api\Operation\FederatedAuthOperations;
use Auth\Presentation\Api\Service\{FederatedFlowCookieService, RefreshTokenCookieService};
use Otp\Application\Service\ChallengeResendPolicy;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Throwable;
use TrustedDevice\Presentation\Api\Service\TrustedDeviceCookieService;

use function hash;
use function implode;
use function is_string;
use function max;
use function substr;
use function time;

/**
 * Processor FederatedCompleteProcessor.
 *
 * Finalizes public sign-in or authenticated provider linking while returning
 * stable client error codes and keeping provider details out of API responses.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<FederatedCompleteInput, LoginOutput|FederatedConnectionsOutput>
 */
final readonly class FederatedCompleteProcessor implements ProcessorInterface
{
  public function __construct(
    private FederatedAuthenticationService $federation,
    private Security $security,
    private RequestStack $requestStack,
    private RefreshTokenCookieService $cookieService,
    private FederatedFlowCookieService $flowCookieService,
    private TrustedDeviceCookieService $trustedDeviceCookieService,
    private LoggerInterface $logger,
    #[Autowire(service: 'limiter.federated_complete')]
    private RateLimiterFactory $rateLimiter,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LoginOutput|FederatedConnectionsOutput
  {
    $provider = $this->provider($uriVariables);
    $request = $this->requestStack->getCurrentRequest();
    $ipAddress = $request?->getClientIp() ?? '127.0.0.1';
    $this->enforceRateLimit($ipAddress);
    $browserBinding = null === $request ? null : $this->flowCookieService->getFromRequest($request);
    if (null !== $request) {
      $request->attributes->set('_federated_flow_clear_cookie', $this->flowCookieService->createClearCookie());
    }

    try {
      if (FederatedAuthOperations::LINK_COMPLETE === $operation->getName()) {
        $user = $this->security->getUser();
        if (!$user instanceof SecurityUser) {
          throw new AccessDeniedHttpException('Authentication required.');
        }

        return FederatedConnectionsOutput::fromContract($this->federation->completeLink(
          $provider,
          $data->state ?? '',
          $data->code ?? '',
          $user->getId(),
          $browserBinding ?? '',
          $data->error,
        ));
      }

      $login = $this->federation->completeLogin(
        provider: $provider,
        state: $data->state ?? '',
        code: $data->code ?? '',
        browserBinding: $browserBinding ?? '',
        providerError: $data->error,
        ipAddress: $ipAddress,
        userAgent: $request?->headers->get('User-Agent'),
        trustedDeviceToken: null === $request ? null : $this->trustedDeviceCookieService->getTokenFromRequest($request),
      );

      $output = new LoginOutput();
      $output->returnUrl = $login->returnUrl;
      $output->newAccount = $login->newAccount;
      $result = $login->login;
      if ($result->mfaRequired) {
        $output->mfaRequired = true;
        $output->mfaToken = $result->mfaToken;
        $output->challengeToken = $result->challengeToken;
        $output->mfaMethod = $result->mfaMethod;
        $output->mfaDestination = $result->mfaDestination;
        $output->mfaResendIn = ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS;

        return $output;
      }

      $output->accessToken = $result->accessToken;
      $output->tokenType = $result->tokenType;
      $output->expiresIn = $result->expiresIn;
      $output->scope = implode(' ', $result->scopes);
      if (null !== $result->refreshToken && null !== $request) {
        $request->attributes->set('_refresh_token_cookie', $this->cookieService->createCookie(
          refreshToken: $result->refreshToken,
          rememberMe: false,
        ));
      }

      return $output;
    } catch (AccessDeniedHttpException $exception) {
      throw $exception;
    } catch (FederatedConflictException $exception) {
      throw new ConflictHttpException($exception->errorCode, $exception);
    } catch (FederatedUnauthorizedException $exception) {
      throw new UnauthorizedHttpException('Bearer', $exception->errorCode, $exception);
    } catch (FederatedAuthException $exception) {
      throw new BadRequestHttpException($exception->errorCode, $exception);
    } catch (Throwable $exception) {
      $this->logger->warning('Federated authentication provider exchange failed.', [
        'provider' => $provider->value,
        'operation' => $operation->getName(),
        'exception_class' => $exception::class,
      ]);

      throw new BadRequestHttpException('federated_auth_failed');
    }
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  private function provider(array $uriVariables): FederatedProvider
  {
    $value = $uriVariables['provider'] ?? null;
    $provider = is_string($value) ? FederatedProvider::tryFrom($value) : null;
    if (null === $provider) {
      throw new BadRequestHttpException('unknown_provider');
    }

    return $provider;
  }

  private function enforceRateLimit(string $ipAddress): void
  {
    $limit = $this->rateLimiter->create(substr(hash('sha256', $ipAddress), 0, 24))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $seconds = max(0, $limit->getRetryAfter()->getTimestamp() - time());

    throw new TooManyRequestsHttpException($seconds, 'Too many authentication attempts.');
  }
}
