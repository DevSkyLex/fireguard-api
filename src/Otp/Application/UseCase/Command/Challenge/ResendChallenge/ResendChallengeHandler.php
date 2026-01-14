<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Challenge\ResendChallenge;

use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{OtpChannel, OtpPurpose};
use Otp\Application\Exception\{OtpNotFoundException, ResendNotAllowedException};
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Application\Service\ChallengeResendPolicy;
use Otp\Application\UseCase\Command\Challenge\GenerateOtp\{GenerateOtpCommand, GenerateOtpHandler};
use Otp\Domain\ValueObject\ChallengeToken;
use Shared\Application\Message\CommandHandler;

/**
 * Handler ResendChallengeHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResendChallengeHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param OtpRepositoryPort $otpRepository the OTP repository
   * @param GenerateOtpHandler $generateHandler the OTP generation handler
   */
  public function __construct(
    private OtpRepositoryPort $otpRepository,
    private GenerateOtpHandler $generateHandler,
  ) {
  }
  // #endregion

  // #region Methods
  public function __invoke(ResendChallengeCommand $command): ResendChallengeResult
  {
    $otp = $this->otpRepository->findByChallengeToken(
      token: ChallengeToken::fromString($command->challengeToken),
    );

    if (null === $otp || 'pending' !== $otp->status()) {
      throw OtpNotFoundException::forIdentifier($command->challengeToken);
    }

    $now = new DateTimeImmutable();
    $canResendIn = ChallengeResendPolicy::canResendIn(
      createdAt: $otp->createdAt(),
      now: $now,
    );

    if ($canResendIn > 0) {
      throw new ResendNotAllowedException(retryAfterSeconds: $canResendIn);
    }

    $generateCommand = new GenerateOtpCommand(
      userId: $command->userId,
      purpose: OtpPurpose::from($otp->purpose()->value),
      channel: OtpChannel::from($otp->channel()->value),
      recipient: $otp->recipient(),
      ttlSeconds: null,
    );

    $result = $this->generateHandler->__invoke($generateCommand);

    return new ResendChallengeResult(
      token: $result->token,
      purpose: $otp->purpose()->value,
      channel: $otp->channel()->value,
      maskedRecipient: $result->maskedRecipient,
      expiresAt: $result->expiresAt,
      maxAttempts: $result->maxAttempts,
      canResendIn: ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS,
    );
  }
  // #endregion
}
