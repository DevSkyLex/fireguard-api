<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DateTimeImmutable;
use Otp\Application\UseCase\Query\GetOtpStatus\GetOtpStatusHandler;
use Otp\Application\UseCase\Query\GetOtpStatus\GetOtpStatusQuery;
use Otp\Domain\Exception\OtpNotFoundException;
use Otp\Presentation\Api\Dto\ChallengeOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
  /**
   * Resend cooldown in seconds.
   */
  private const int RESEND_COOLDOWN_SECONDS = 60;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @param GetOtpStatusHandler $handler the handler
   */
  public function __construct(
    private GetOtpStatusHandler $handler,
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
      // For now, use token as OTP ID (TODO: lookup by challenge token)
      $query = new GetOtpStatusQuery(otpId: $token);
      $result = $this->handler->__invoke($query);

      // Calculate times
      $now = new DateTimeImmutable();
      $expiresIn = max(0, $result->expiresAt->getTimestamp() - $now->getTimestamp());

      // Calculate resend cooldown (simplified - should track actual resend time)
      $canResendIn = 0;
      if ('pending' === $result->status) {
        $createdAt = $result->createdAt ?? $now;
        $elapsed = $now->getTimestamp() - $createdAt->getTimestamp();
        $canResendIn = max(0, self::RESEND_COOLDOWN_SECONDS - $elapsed);
      }

      $output = new ChallengeOutput();
      $output->token = $token;
      $output->purpose = $result->purpose;
      $output->channel = $result->channel;
      $output->maskedRecipient = $result->maskedRecipient;
      $output->status = $result->status;
      $output->expiresIn = $expiresIn;
      $output->attemptsRemaining = $result->attemptsRemaining;
      $output->canResendIn = $canResendIn;

      return $output;
    } catch (OtpNotFoundException) {
      throw new NotFoundHttpException('Challenge not found.');
    }
  }
  // #endregion
}
