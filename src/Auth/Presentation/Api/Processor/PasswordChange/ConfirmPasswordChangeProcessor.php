<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\PasswordChange;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\PasswordChange\ConfirmPasswordChange\{ConfirmPasswordChangeCommand, ConfirmPasswordChangeResult};
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Api\Dto\Input\PasswordChange\ConfirmPasswordChangeInput;
use Auth\Presentation\Api\Dto\Output\PasswordChange\ConfirmPasswordChangeOutput;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  TooManyRequestsHttpException,
  UnauthorizedHttpException
};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function hash;
use function max;
use function sprintf;
use function substr;
use function time;

/**
 * Processor ConfirmPasswordChangeProcessor.
 *
 * Verifies the OTP code and applies the password change for the
 * authenticated user.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ConfirmPasswordChangeInput, ConfirmPasswordChangeOutput>
 */
final readonly class ConfirmPasswordChangeProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ConfirmPasswordChangeProcessor class.
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
    #[Autowire(service: 'limiter.password_change_confirm')]
    private ?RateLimiterFactory $rateLimiter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Process the password change confirmation.
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation
   * @param array<mixed> $uriVariables URI variables
   * @param array<mixed> $context processing context
   *
   * @throws AccessDeniedHttpException when not authenticated
   * @throws UnauthorizedHttpException when token/code is invalid
   * @throws TooManyRequestsHttpException when max attempts exceeded
   * @throws BadRequestHttpException when request is malformed
   *
   * @return ConfirmPasswordChangeOutput the output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConfirmPasswordChangeOutput
  {
    if (!$data instanceof ConfirmPasswordChangeInput) {
      throw new InvalidArgumentException('Invalid input data');
    }

    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $token = $data->token ?? '';
    $this->enforceRateLimit($user->getId(), $token);

    $command = new ConfirmPasswordChangeCommand(
      userId: $user->getId(),
      token: $token,
      code: $data->code ?? '',
      newPassword: $data->newPassword ?? '',
      ipAddress: $this->requestStack->getCurrentRequest()?->getClientIp(),
    );

    /** @var ConfirmPasswordChangeResult $result */
    $result = $this->commandBus->dispatch($command);

    if (!$result->success) {
      $this->handleError($result);
    }

    return new ConfirmPasswordChangeOutput(
      success: $result->success,
      message: $result->message ?? 'Password has been changed successfully.',
      errorCode: $result->errorCode,
      attemptsRemaining: $result->attemptsRemaining,
    );
  }

  /**
   * Handle error responses.
   *
   * @throws UnauthorizedHttpException
   * @throws TooManyRequestsHttpException
   * @throws BadRequestHttpException
   */
  private function handleError(ConfirmPasswordChangeResult $result): void
  {
    match ($result->errorCode) {
      ConfirmPasswordChangeResult::ERROR_MAX_ATTEMPTS => throw new TooManyRequestsHttpException(
        null,
        $result->message ?? 'Maximum verification attempts exceeded.',
      ),
      ConfirmPasswordChangeResult::ERROR_EXPIRED,
      ConfirmPasswordChangeResult::ERROR_INVALID_TOKEN,
      ConfirmPasswordChangeResult::ERROR_INVALID_CODE => throw new UnauthorizedHttpException(
        '',
        $result->message ?? 'Invalid or expired token/code.',
      ),
      default => throw new BadRequestHttpException(
        $result->message ?? 'Password change failed.',
      ),
    };
  }

  private function enforceRateLimit(string $userId, string $token): void
  {
    if (null === $this->rateLimiter) {
      return;
    }

    $limit = $this->rateLimiter->create($this->getRateLimitKey($userId, $token))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many password change confirmation attempts. Please try again in %d seconds.', $seconds),
    );
  }

  private function getRateLimitKey(string $userId, string $token): string
  {
    $userHash = hash('sha256', $userId);
    $tokenHash = hash('sha256', $token);

    return sprintf('password_change_confirm_%s_%s', substr($userHash, 0, 16), substr($tokenHash, 0, 16));
  }
  // #endregion
}
