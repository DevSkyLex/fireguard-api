<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Processor\Totp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Otp\Application\Exception\TotpPendingEnrollmentNotFoundException;
use Otp\Application\UseCase\Command\Totp\ConfirmTotp\{ConfirmTotpCommand, ConfirmTotpResult};
use Otp\Presentation\Api\Dto\Input\Totp\ConfirmTotpInput;
use Otp\Presentation\Api\Dto\Output\Totp\ConfirmTotpOutput;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{NotFoundHttpException, TooManyRequestsHttpException, UnauthorizedHttpException, UnprocessableEntityHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Throwable;

use function hash;
use function is_string;
use function max;
use function method_exists;
use function sprintf;
use function substr;
use function time;

/**
 * Processor ConfirmTotpProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ConfirmTotpInput, ConfirmTotpOutput>
 */
final readonly class ConfirmTotpProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   * @param RateLimiterFactory|null $rateLimiter the confirm rate limiter
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    #[Autowire(service: 'limiter.otp_totp_confirm')]
    private readonly ?RateLimiterFactory $rateLimiter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @param ConfirmTotpInput $data
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConfirmTotpOutput
  {
    $user = $this->security->getUser();
    if (null === $user) {
      throw new UnauthorizedHttpException('Bearer', 'User must be authenticated.');
    }

    // Enrollment rows are keyed by the immutable user UUID (matching the
    // login-time TotpEnrollmentCheckPort and /api/me status lookups), never by
    // the mutable email that getUserIdentifier() returns.
    $idRaw = method_exists($user, 'getId') ? $user->getId() : null;
    $userId = is_string($idRaw) ? $idRaw : $user->getUserIdentifier();

    $this->enforceRateLimit($userId);

    try {
      $command = new ConfirmTotpCommand(
        userId: $userId,
        code: $data->code,
      );

      /** @var ConfirmTotpResult $result */
      $result = $this->commandBus->dispatch($command);
    } catch (Throwable $exception) {
      $notFound = $this->extractPendingEnrollmentNotFoundException($exception);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage());
      }

      throw $exception;
    }

    if (!$result->success) {
      throw new UnprocessableEntityHttpException($result->error ?? 'Invalid TOTP code.');
    }

    $output = new ConfirmTotpOutput();
    $output->success = true;

    return $output;
  }

  private function extractPendingEnrollmentNotFoundException(Throwable $exception): ?TotpPendingEnrollmentNotFoundException
  {
    if ($exception instanceof TotpPendingEnrollmentNotFoundException) {
      return $exception;
    }

    if ($exception instanceof HandlerFailedException) {
      foreach ($exception->getWrappedExceptions() as $nestedException) {
        if ($nestedException instanceof TotpPendingEnrollmentNotFoundException) {
          return $nestedException;
        }
      }

      return null;
    }

    if ($exception instanceof MessengerRuntimeException) {
      $previous = $exception->getPrevious();

      if ($previous instanceof HandlerFailedException) {
        foreach ($previous->getWrappedExceptions() as $nestedException) {
          if ($nestedException instanceof TotpPendingEnrollmentNotFoundException) {
            return $nestedException;
          }
        }
      }

      while ($previous) {
        if ($previous instanceof TotpPendingEnrollmentNotFoundException) {
          return $previous;
        }

        $previous = $previous->getPrevious();
      }
    }

    return null;
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
      sprintf('Too many TOTP confirmation attempts. Please try again in %d seconds.', $seconds),
    );
  }

  private function getRateLimitKey(string $userId): string
  {
    return sprintf('otp_totp_confirm_%s', substr(hash('sha256', $userId), 0, 16));
  }
  // #endregion
}
