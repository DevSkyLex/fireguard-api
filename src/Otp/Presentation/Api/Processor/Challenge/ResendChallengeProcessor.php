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
      $otpNotFound = self::extractWrapped($exception, OtpNotFoundException::class);
      if ($otpNotFound instanceof OtpNotFoundException) {
        throw new NotFoundHttpException('Challenge not found.');
      }

      $resendNotAllowed = self::extractWrapped($exception, ResendNotAllowedException::class);
      if ($resendNotAllowed instanceof ResendNotAllowedException) {
        throw new TooManyRequestsHttpException(
          $resendNotAllowed->retryAfterSeconds(),
          "Please wait {$resendNotAllowed->retryAfterSeconds()} seconds before resending.",
        );
      }

      throw $exception;
    }
  }

  /**
   * @template T of Throwable
   *
   * @param Throwable $exception the bus failure
   * @param class-string<T> $type the domain exception to find
   *
   * @return ?T the matching exception, when present
   */
  private static function extractWrapped(Throwable $exception, string $type): ?Throwable
  {
    if ($exception instanceof $type) {
      return $exception;
    }
    if ($exception instanceof HandlerFailedException) {
      return self::findInHandler($exception, $type);
    }
    if ($exception instanceof MessengerRuntimeException) {
      return self::findInRuntime($exception, $type);
    }

    return null;
  }

  /**
   * @template T of Throwable
   *
   * @param HandlerFailedException $exception the wrapped handler failure
   * @param class-string<T> $type the domain exception to find
   *
   * @return ?T the matching wrapped exception
   */
  private static function findInHandler(HandlerFailedException $exception, string $type): ?Throwable
  {
    foreach ($exception->getWrappedExceptions() as $nestedException) {
      if ($nestedException instanceof $type) {
        return $nestedException;
      }
    }

    return null;
  }

  /**
   * @template T of Throwable
   *
   * @param MessengerRuntimeException $exception the outer bus failure
   * @param class-string<T> $type the domain exception to find
   *
   * @return ?T the matching nested or previous exception
   */
  private static function findInRuntime(MessengerRuntimeException $exception, string $type): ?Throwable
  {
    $previous = $exception->getPrevious();
    if ($previous instanceof HandlerFailedException) {
      $nested = self::findInHandler($previous, $type);
      if (null !== $nested) {
        return $nested;
      }
    }

    while ($previous) {
      if ($previous instanceof $type) {
        return $previous;
      }
      $previous = $previous->getPrevious();
    }

    return null;
  }
  // #endregion
}
