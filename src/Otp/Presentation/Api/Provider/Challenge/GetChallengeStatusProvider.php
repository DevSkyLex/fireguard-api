<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Provider\Challenge;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DateTimeImmutable;
use Otp\Application\Exception\OtpNotFoundException;
use Otp\Application\UseCase\Query\Challenge\GetChallengeStatus\{GetChallengeStatusQuery, GetChallengeStatusResult};
use Otp\Presentation\Api\Dto\Output\Challenge\ChallengeOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;
use function max;

/**
 * Provider GetChallengeStatusProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<ChallengeOutput>
 */
final readonly class GetChallengeStatusProvider implements ProviderInterface
{
  // #region Constants
  // #region Constructor
  /**
   * Constructor.
   *
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ChallengeOutput
  {
    $token = $uriVariables['token'] ?? null;

    if (!is_string($token)) {
      throw new NotFoundHttpException('Challenge token is required.');
    }

    try {
      $query = new GetChallengeStatusQuery(challengeToken: $token);
      /** @var GetChallengeStatusResult $result */
      $result = $this->queryBus->ask($query);

      // Calculate times
      $now = new DateTimeImmutable();
      $expiresIn = max(0, $result->expiresAt->getTimestamp() - $now->getTimestamp());

      $output = new ChallengeOutput();
      $output->token = $token;
      $output->purpose = $result->purpose;
      $output->channel = $result->channel;
      $output->maskedRecipient = $result->maskedRecipient;
      $output->status = $result->status;
      $output->expiresIn = $expiresIn;
      $output->attemptsRemaining = $result->attemptsRemaining;
      $output->canResendIn = $result->canResendIn;

      return $output;
    } catch (Throwable $exception) {
      $otpNotFound = $this->extractOtpNotFoundException($exception);
      if (null !== $otpNotFound) {
        throw new NotFoundHttpException(
          message: 'Challenge not found.',
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
  // #endregion
}
