<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Processor\Challenge;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateTimeImmutable;
use Otp\Application\Exception\{OtpNotFoundException, ResendNotAllowedException};
use Otp\Application\UseCase\Command\Challenge\ResendChallenge\{ResendChallengeCommand, ResendChallengeResult};
use Otp\Presentation\Api\Dto\Output\Challenge\ChallengeOutput;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{NotFoundHttpException, TooManyRequestsHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;
use function max;

/**
 * Processor ResendChallengeProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, ChallengeOutput>
 */
final readonly class ResendChallengeProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChallengeOutput
  {
    $token = $uriVariables['token'] ?? null;

    if (!is_string($token)) {
      throw new NotFoundHttpException('Challenge token is required.');
    }

    $user = $this->security->getUser();
    if (null === $user) {
      throw new NotFoundHttpException('User must be authenticated.');
    }

    try {
      $command = new ResendChallengeCommand(
        challengeToken: $token,
        userId: $user->getUserIdentifier(),
      );

      /** @var ResendChallengeResult $result */
      $result = $this->commandBus->dispatch($command);

      $now = new DateTimeImmutable();
      $expiresIn = max(0, $result->expiresAt->getTimestamp() - $now->getTimestamp());

      $output = new ChallengeOutput();
      $output->token = $result->token;
      $output->purpose = $result->purpose;
      $output->channel = $result->channel;
      $output->maskedRecipient = $result->maskedRecipient;
      $output->status = 'pending';
      $output->expiresIn = $expiresIn;
      $output->attemptsRemaining = $result->maxAttempts;
      $output->canResendIn = $result->canResendIn;

      return $output;
    } catch (Throwable $exception) {
      $otpNotFound = $this->extractOtpNotFoundException($exception);
      if (null !== $otpNotFound) {
        throw new NotFoundHttpException('Challenge not found.');
      }

      $resendNotAllowed = $this->extractResendNotAllowedException($exception);
      if (null !== $resendNotAllowed) {
        throw new TooManyRequestsHttpException(
          $resendNotAllowed->retryAfterSeconds(),
          "Please wait {$resendNotAllowed->retryAfterSeconds()} seconds before resending.",
        );
      }

      throw $exception;
    }
  }

  private function extractOtpNotFoundException(Throwable $exception): ?OtpNotFoundException
  {
    if ($exception instanceof OtpNotFoundException) {
      return $exception;
    }

    if ($exception instanceof HandlerFailedException) {
      foreach ($exception->getWrappedExceptions() as $nestedException) {
        if ($nestedException instanceof OtpNotFoundException) {
          return $nestedException;
        }
      }

      return null;
    }

    if ($exception instanceof MessengerRuntimeException) {
      $previous = $exception->getPrevious();

      if ($previous instanceof HandlerFailedException) {
        foreach ($previous->getWrappedExceptions() as $nestedException) {
          if ($nestedException instanceof OtpNotFoundException) {
            return $nestedException;
          }
        }
      }

      while ($previous) {
        if ($previous instanceof OtpNotFoundException) {
          return $previous;
        }

        $previous = $previous->getPrevious();
      }
    }

    return null;
  }

  private function extractResendNotAllowedException(Throwable $exception): ?ResendNotAllowedException
  {
    if ($exception instanceof ResendNotAllowedException) {
      return $exception;
    }

    if ($exception instanceof HandlerFailedException) {
      foreach ($exception->getWrappedExceptions() as $nestedException) {
        if ($nestedException instanceof ResendNotAllowedException) {
          return $nestedException;
        }
      }

      return null;
    }

    if ($exception instanceof MessengerRuntimeException) {
      $previous = $exception->getPrevious();

      if ($previous instanceof HandlerFailedException) {
        foreach ($previous->getWrappedExceptions() as $nestedException) {
          if ($nestedException instanceof ResendNotAllowedException) {
            return $nestedException;
          }
        }
      }

      while ($previous) {
        if ($previous instanceof ResendNotAllowedException) {
          return $previous;
        }

        $previous = $previous->getPrevious();
      }
    }

    return null;
  }
  // #endregion
}
