<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Processor\Totp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Otp\Application\Exception\TotpEnrollmentNotEnabledException;
use Otp\Application\UseCase\Command\Totp\DisableTotp\{DisableTotpCommand, DisableTotpResult};
use Otp\Domain\Exception\TotpDisableTemporarilyLockedException;
use Otp\Presentation\Api\Dto\Input\Totp\DisableTotpInput;
use Otp\Presentation\Api\Dto\Output\Totp\DisableTotpOutput;
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
 * Processor DisableTotpProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<DisableTotpInput, DisableTotpOutput>
 */
final readonly class DisableTotpProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   * @param RateLimiterFactory|null $rateLimiter the disable rate limiter
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    #[Autowire(service: 'limiter.otp_totp_disable')]
    private readonly ?RateLimiterFactory $rateLimiter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @param DisableTotpInput $data
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DisableTotpOutput
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
      $command = new DisableTotpCommand(
        userId: $userId,
        code: $data->code,
      );

      /** @var DisableTotpResult $result */
      $result = $this->commandBus->dispatch($command);
    } catch (Throwable $exception) {
      $notEnabled = $this->extractWrapped($exception, TotpEnrollmentNotEnabledException::class);
      if (null !== $notEnabled) {
        throw new NotFoundHttpException($notEnabled->getMessage());
      }

      // The per-enrollment cooldown, distinct from the request rate limiter
      // above: that one throttles bursts, this one survives across them and is
      // what actually bounds a slow, patient guessing run.
      $locked = $this->extractWrapped($exception, TotpDisableTemporarilyLockedException::class);
      if ($locked instanceof TotpDisableTemporarilyLockedException) {
        throw new TooManyRequestsHttpException($locked->retryAfterSeconds, $locked->getMessage());
      }

      throw $exception;
    }

    if (!$result->success) {
      throw new UnprocessableEntityHttpException($result->error ?? 'Invalid TOTP code.');
    }

    $output = new DisableTotpOutput();
    $output->success = true;

    return $output;
  }

  /**
   * Method extractWrapped.
   *
   * Digs a domain exception of the requested class out of the bus wrappers.
   *
   * @since 1.1.0
   *
   * @template T of Throwable
   *
   * @param Throwable $exception the exception as caught
   * @param class-string<T> $type the domain exception class to look for
   *
   * @return T|null the unwrapped exception, or null when absent
   */
  private function extractWrapped(Throwable $exception, string $type): ?Throwable
  {
    if ($exception instanceof $type) {
      return $exception;
    }

    if ($exception instanceof HandlerFailedException) {
      foreach ($exception->getWrappedExceptions() as $nestedException) {
        if ($nestedException instanceof $type) {
          return $nestedException;
        }
      }

      return null;
    }

    if ($exception instanceof MessengerRuntimeException) {
      $previous = $exception->getPrevious();

      if ($previous instanceof HandlerFailedException) {
        foreach ($previous->getWrappedExceptions() as $nestedException) {
          if ($nestedException instanceof $type) {
            return $nestedException;
          }
        }
      }

      while ($previous) {
        if ($previous instanceof $type) {
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
      sprintf('Too many TOTP disable attempts. Please try again in %d seconds.', $seconds),
    );
  }

  private function getRateLimitKey(string $userId): string
  {
    return sprintf('otp_totp_disable_%s', substr(hash('sha256', $userId), 0, 16));
  }
  // #endregion
}
