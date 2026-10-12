<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Federation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\Federation\{FederatedLogin, FederatedLoginContext, ProviderId};
use Auth\Application\Contract\User\AuthenticatedUser;
use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Application\UseCase\Command\Federation\CompleteFederation\CompleteFederationCommand;
use Auth\Presentation\Api\Dto\Input\Federation\FederatedCompleteInput;
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use Auth\Presentation\Api\Dto\Output\Federation\FederatedConnectionsOutput;
use Auth\Presentation\Api\Operation\FederatedAuthOperations;
use Auth\Presentation\Api\Service\{FederatedFlowCookieService, RefreshTokenCookieService};
use Auth\Presentation\Api\Service\TrustedDeviceCookieService;
use Otp\Application\Port\Inbound\Challenge\ChallengeAccessPort;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function hash;
use function implode;
use function is_string;
use function max;
use function substr;
use function time;

/**
 * Class FederatedCompleteProcessor
 *
 * Finalizes public sign-in or authenticated provider linking while returning
 * stable client error codes and keeping provider details out of API responses.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<FederatedCompleteInput, LoginOutput|FederatedConnectionsOutput>
 */
final readonly class FederatedCompleteProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the FederatedCompleteProcessor dependencies and state.
   *
   * @access public
   *
   * @param CommandBusPort $federation the federation
   * @param Security $security the security
   * @param RequestStack $requestStack the request stack
   * @param RefreshTokenCookieService $cookieService the cookie service
   * @param FederatedFlowCookieService $flowCookieService the flow cookie service
   * @param TrustedDeviceCookieService $trustedDeviceCookieService the trusted device cookie service
   * @param RateLimiterFactory $rateLimiter the rate limiter
   *
   * @return void
   */
  public function __construct(
    private CommandBusPort $federation,
    private Security $security,
    private RequestStack $requestStack,
    private RefreshTokenCookieService $cookieService,
    private FederatedFlowCookieService $flowCookieService,
    private TrustedDeviceCookieService $trustedDeviceCookieService,
    #[Autowire(service: 'limiter.federated_complete')]
    private RateLimiterFactory $rateLimiter,
    private ?JwtTokenServicePort $tokens = null,
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method process
   *
   * Processes the API operation by translating the request into the corresponding application action.
   *
   * @access public
   *
   * @param FederatedCompleteInput $data provider callback and flow state fields
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables route variables containing the provider
   * @param array<string, mixed> $context processor context supplied by API Platform
   *
   * @return LoginOutput|FederatedConnectionsOutput
   */
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

    if (FederatedAuthOperations::LINK_COMPLETE === $operation->getName()) {
      $user = $this->security->getUser();
      if (!$user instanceof AuthenticatedUser) {
        throw new AccessDeniedHttpException('Authentication required.');
      }

      /** @var \Auth\Application\UseCase\Command\Federation\CompleteFederation\CompleteFederationResult $completion */
      $completion = $this->federation->dispatch(new CompleteFederationCommand(
        provider: $provider,
        state: $data->state ?? '',
        code: $data->code ?? '',
        browserBinding: $browserBinding ?? '',
        providerError: $data->error,
        loginContext: new FederatedLoginContext(ipAddress: $ipAddress, userAgent: null, trustedDeviceToken: null),
        userId: $user->getId(),
      ));
      if (null === $completion->connections) {
        throw new BadRequestHttpException('federated_auth_failed');
      }
      if (null !== $request && null !== $browserBinding && null !== $completion->connections->browserProofExpiresAt) {
        $request->attributes->remove('_federated_flow_clear_cookie');
        $request->attributes->set('_federated_flow_cookie', $this->flowCookieService->createReplayCookie($browserBinding, $completion->connections->browserProofExpiresAt));
      }

      return FederatedConnectionsOutput::fromContract($completion->connections);
    }

    /** @var \Auth\Application\UseCase\Command\Federation\CompleteFederation\CompleteFederationResult $completion */
    $completion = $this->federation->dispatch(new CompleteFederationCommand(
      provider: $provider,
      state: $data->state ?? '',
      code: $data->code ?? '',
      browserBinding: $browserBinding ?? '',
      providerError: $data->error,
      loginContext: new FederatedLoginContext(
        ipAddress: $ipAddress,
        userAgent: \Auth\Presentation\Api\Http\OperationParameterReader::headers($operation, $request)->get('User-Agent'),
        trustedDeviceToken: null === $request ? null : $this->trustedDeviceCookieService->getTokenFromRequest($request),
      ),
    ));
    $login = $completion->login;
    if (null === $login) {
      throw new BadRequestHttpException('federated_auth_failed');
    }
    if (null !== $request && null !== $browserBinding && null !== $login->browserProofExpiresAt) {
      $request->attributes->remove('_federated_flow_clear_cookie');
      $request->attributes->set('_federated_flow_cookie', $this->flowCookieService->createReplayCookie($browserBinding, $login->browserProofExpiresAt));
    }

    return $this->loginOutput($login, $request);
  }

  /**
   * Method loginOutput
   *
   * Projects the session result and retains the absolute expiration of any refresh cookie.
   *
   * @access private
   *
   * @param FederatedLogin $login the completed sign-in result
   * @param ?Request $request the current request receiving refresh-cookie attributes
   *
   * @return LoginOutput the existing token or MFA response shape
   */
  private function loginOutput(FederatedLogin $login, ?Request $request): LoginOutput
  {
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
      $output->mfaResendIn = ChallengeAccessPort::RESEND_COOLDOWN_SECONDS;

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
        absoluteExpiresAt: $this->tokens?->decodeRefreshToken($result->refreshToken)['expires_at'] ?? null,
      ));
    }

    return $output;
  }

  /**
   * Method provider
   *
   * Resolves the provider route value to a supported federated identity provider.
   *
   * @access private
   *
   * @param array<string, mixed> $uriVariables API Platform route variables
   *
   * @return ProviderId selected provider
   *
   * @throws BadRequestHttpException when the route provider is unknown
   */
  private function provider(array $uriVariables): ProviderId
  {
    $value = $uriVariables['provider'] ?? null;
    $provider = is_string($value) ? ProviderId::tryFrom($value) : null;
    if (null === $provider) {
      throw new BadRequestHttpException('unknown_provider');
    }

    return $provider;
  }

  /**
   * Method enforceRateLimit
   *
   * Enforces the endpoint rate limit for the supplied recipient or request context.
   *
   * @access private
   *
   * @param string $ipAddress the client IP address
   *
   * @return void
   */
  private function enforceRateLimit(string $ipAddress): void
  {
    $limit = $this->rateLimiter->create(substr(hash('sha256', $ipAddress), 0, 24))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $seconds = max(0, $limit->getRetryAfter()->getTimestamp() - time());

    throw new TooManyRequestsHttpException($seconds, 'Too many authentication attempts.');
  }
  // #endregion
}
