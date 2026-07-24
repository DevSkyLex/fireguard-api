<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\PasswordChange;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\PasswordChange\RequestPasswordChange\{RequestPasswordChangeCommand, RequestPasswordChangeResult};
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Api\Dto\Input\PasswordChange\RequestPasswordChangeInput;
use Auth\Presentation\Api\Dto\Output\PasswordChange\RequestPasswordChangeOutput;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, TooManyRequestsHttpException, UnprocessableEntityHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function hash;
use function max;
use function sprintf;
use function substr;
use function time;

/**
 * Processor RequestPasswordChangeProcessor.
 *
 * Verifies the authenticated user's current password and triggers
 * the OTP challenge email for the password change confirmation step.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<RequestPasswordChangeInput, RequestPasswordChangeOutput>
 */
final readonly class RequestPasswordChangeProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RequestPasswordChangeProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   * @param RequestStack $requestStack the request stack
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private RequestStack $requestStack,
    #[Autowire(service: 'limiter.password_change_request')]
    private ?RateLimiterFactory $rateLimiter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Process the password change request.
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation
   * @param array<mixed> $uriVariables URI variables
   * @param array<mixed> $context processing context
   *
   * @throws AccessDeniedHttpException when not authenticated
   * @throws UnprocessableEntityHttpException when the current password is incorrect
   * @throws TooManyRequestsHttpException when rate limited
   *
   * @return RequestPasswordChangeOutput the output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RequestPasswordChangeOutput
  {
    if (!$data instanceof RequestPasswordChangeInput) {
      throw new InvalidArgumentException('Invalid input data');
    }

    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $this->enforceRateLimit($user->getId());

    $command = new RequestPasswordChangeCommand(
      userId: $user->getId(),
      currentPassword: $data->currentPassword ?? '',
      ipAddress: $this->requestStack->getCurrentRequest()?->getClientIp(),
    );

    /** @var RequestPasswordChangeResult $result */
    $result = $this->commandBus->dispatch($command);

    if (!$result->success) {
      throw new UnprocessableEntityHttpException(
        $result->message ?? 'Password change request failed.',
      );
    }

    return new RequestPasswordChangeOutput(
      success: $result->success,
      message: $result->message ?? 'A verification code has been sent to your email address.',
      challengeToken: $result->challengeToken,
      maskedRecipient: $result->maskedRecipient,
      expiresAt: $result->expiresAt,
      maxAttempts: $result->maxAttempts,
    );
  }

  private function enforceRateLimit(string $userId): void
  {
    if (null === $this->rateLimiter) {
      return;
    }

    $limit = $this->rateLimiter->create($this->getRateLimitKey($userId))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many password change requests. Please try again in %d seconds.', $seconds),
    );
  }

  private function getRateLimitKey(string $userId): string
  {
    $userHash = hash('sha256', $userId);

    return sprintf('password_change_request_%s', substr($userHash, 0, 16));
  }
  // #endregion
}
