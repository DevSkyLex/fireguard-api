<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Federation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Service\Federation\InitialPasswordService;
use Auth\Domain\Exception\Federation\{FederatedAuthException, FederatedConflictException, FederatedUnauthorizedException};
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Api\Dto\Output\PasswordChange\RequestPasswordChangeOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function max;
use function time;

/**
 * @implements ProcessorInterface<void, RequestPasswordChangeOutput>
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPasswordSetupProcessor implements ProcessorInterface
{
  public function __construct(
    private InitialPasswordService $passwordSetup,
    private Security $security,
    #[Autowire(service: 'limiter.password_setup')]
    private RateLimiterFactory $rateLimiter,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RequestPasswordChangeOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $limit = $this->rateLimiter->create($user->getId())->consume();
    if (!$limit->isAccepted()) {
      throw new TooManyRequestsHttpException(max(0, $limit->getRetryAfter()->getTimestamp() - time()));
    }

    try {
      $challenge = $this->passwordSetup->request($user->getId());
    } catch (FederatedConflictException $exception) {
      throw new ConflictHttpException($exception->errorCode, $exception);
    } catch (FederatedUnauthorizedException $exception) {
      throw new UnauthorizedHttpException('Bearer', $exception->errorCode, $exception);
    } catch (FederatedAuthException $exception) {
      throw new BadRequestHttpException($exception->errorCode, $exception);
    }

    return new RequestPasswordChangeOutput(
      success: true,
      message: 'A verification code has been sent.',
      challengeToken: $challenge->challengeToken,
      maskedRecipient: $challenge->maskedRecipient,
      expiresAt: $challenge->expiresAt,
      maxAttempts: $challenge->maxAttempts,
    );
  }
}
