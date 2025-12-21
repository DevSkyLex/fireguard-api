<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\GenerateOtp;

use Otp\Application\Port\Outbound\OtpNotifierPort;
use Otp\Application\Port\Outbound\OtpRepositoryPort;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\OtpId;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;

/**
 * Handler GenerateOtpHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GenerateOtpHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GenerateOtpHandler class.
   *
   * @since 1.0.0
   *
   * @param OtpRepositoryPort $otpRepository the OTP repository
   * @param OtpNotifierPort $otpNotifier the OTP notifier
   * @param UuidFactory $uuidFactory the UUID factory
   */
  public function __construct(
    private readonly OtpRepositoryPort $otpRepository,
    private readonly OtpNotifierPort $otpNotifier,
    private readonly UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the GenerateOtpCommand.
   *
   * @since 1.0.0
   *
   * @param GenerateOtpCommand $command the command
   *
   * @return GenerateOtpResult the result
   */
  public function __invoke(GenerateOtpCommand $command): GenerateOtpResult
  {
    // Revoke any existing pending OTPs for this user/purpose
    $this->otpRepository->revokeAllForUser(
      userId: $command->userId,
      purpose: $command->purpose,
    );

    // Generate new OTP
    $otpId = $this->uuidFactory->create(OtpId::class);

    $otp = Otp::generate(
      id: $otpId,
      userId: $command->userId,
      purpose: $command->purpose,
      channel: $command->channel,
      recipient: $command->recipient,
      ttlSeconds: $command->ttlSeconds,
      maxAttempts: $command->maxAttempts,
    );

    // Persist OTP
    $this->otpRepository->save(otp: $otp);

    // Send notification (if channel requires it)
    if ($command->channel->requiresDelivery()) {
      $this->otpNotifier->send(otp: $otp);
    }

    return new GenerateOtpResult(
      otpId: $otpId->value,
      token: $otp->challengeToken()->value,
      maskedRecipient: $otp->maskedRecipient(),
      expiresAt: $otp->expiresAt(),
      maxAttempts: $otp->maxAttempts(),
    );
  }
  // #endregion
}
