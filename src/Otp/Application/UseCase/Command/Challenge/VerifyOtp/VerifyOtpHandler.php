<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Challenge\VerifyOtp;

use InvalidArgumentException;
use Otp\Application\Exception\OtpNotFoundException;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Application\Port\Outbound\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Domain\Exception\{OtpExpiredException, OtpMaxAttemptsException};
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpId};
use Shared\Application\Message\CommandHandler;

/**
 * Handler VerifyOtpHandler.
 *
 * For TOTP-channel challenges (used by login MFA once a user has an active
 * authenticator enrollment), the submitted code is checked against the
 * user's active TOTP secret instead of the challenge's own random code.
 * Challenge-level expiry and attempt bookkeeping still apply.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class VerifyOtpHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * VerifyOtpHandler class.
   *
   * @param OtpRepositoryPort $otpRepository the OTP repository
   * @param TotpEnrollmentRepositoryPort $totpEnrollmentRepository the TOTP enrollment repository
   * @param TotpServicePort $totpService the TOTP service
   */
  public function __construct(
    private readonly OtpRepositoryPort $otpRepository,
    private readonly TotpEnrollmentRepositoryPort $totpEnrollmentRepository,
    private readonly TotpServicePort $totpService,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the VerifyOtpCommand.
   *
   * @since 1.0.0
   *
   * @param VerifyOtpCommand $command the command
   *
   * @throws OtpNotFoundException if OTP not found
   *
   * @return VerifyOtpResult the result
   */
  public function __invoke(VerifyOtpCommand $command): VerifyOtpResult
  {
    $otp = match (true) {
      null !== $command->otpId => $this->otpRepository->findById(new OtpId($command->otpId)),
      null !== $command->challengeToken => $this->otpRepository->findByChallengeToken(
        ChallengeToken::fromString($command->challengeToken),
      ),
      default => throw new InvalidArgumentException('Either OTP ID or Challenge Token must be provided.'),
    };

    if (null === $otp) {
      throw OtpNotFoundException::forIdentifier($command->otpId ?? $command->challengeToken ?? 'unknown');
    }

    try {
      $verified = OtpChannel::TOTP === $otp->channel()
        ? $otp->verifyExternal($this->isValidTotpCode($otp, $command->code))
        : $otp->verify($command->code);

      // Persist updated state
      $this->otpRepository->save($otp);

      if ($verified) {
        return VerifyOtpResult::success();
      }

      return VerifyOtpResult::failed(
        attemptsRemaining: $otp->attemptsRemaining(),
        error: 'Invalid verification code.',
      );
    } catch (OtpExpiredException) {
      return VerifyOtpResult::failed(
        attemptsRemaining: 0,
        error: 'OTP has expired.',
      );
    } catch (OtpMaxAttemptsException) {
      return VerifyOtpResult::failed(
        attemptsRemaining: 0,
        error: 'Maximum verification attempts exceeded.',
      );
    }
  }

  /**
   * Method isValidTotpCode.
   *
   * Verifies the submitted code against the user's active TOTP secret.
   *
   * @since 1.0.0
   *
   * @param Otp $otp the OTP challenge (channel = TOTP)
   * @param string $code the submitted code
   *
   * @return bool true if the code matches the active TOTP secret
   */
  private function isValidTotpCode(Otp $otp, string $code): bool
  {
    $enrollment = $this->totpEnrollmentRepository->findByUserId($otp->userId());
    $activeSecret = $enrollment?->activeSecret();

    if (null === $activeSecret) {
      return false;
    }

    return $this->totpService->verify($code, $activeSecret);
  }
  // #endregion
}
