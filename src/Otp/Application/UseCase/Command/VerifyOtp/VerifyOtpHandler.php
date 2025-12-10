<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\VerifyOtp;

use Otp\Application\Port\Outbound\OtpRepositoryPort;
use Otp\Domain\Exception\OtpExpiredException;
use Otp\Domain\Exception\OtpMaxAttemptsException;
use Otp\Domain\Exception\OtpNotFoundException;
use Otp\Domain\ValueObject\ChallengeToken;
use Otp\Domain\ValueObject\OtpId;
use Shared\Application\Message\CommandHandler;

/**
 * Handler VerifyOtpHandler
 * @final
 *
 * Handles OTP verification.
 *
 * @category Handler
 * @package Otp\Application\UseCase\Command\VerifyOtp
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class VerifyOtpHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * VerifyOtpHandler class.
   *
   * @param OtpRepositoryPort $otpRepository The OTP repository.
   */
  public function __construct(
    private readonly OtpRepositoryPort $otpRepository,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the VerifyOtpCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param VerifyOtpCommand $command The command.
   *
   * @return VerifyOtpResult The result.
   *
   * @throws OtpNotFoundException If OTP not found.
   */
  public function __invoke(VerifyOtpCommand $command): VerifyOtpResult
  {
    $otp = match (true) {
      $command->otpId !== null => $this->otpRepository->findById(new OtpId($command->otpId)),
      $command->challengeToken !== null => $this->otpRepository->findByChallengeToken(
        ChallengeToken::fromString($command->challengeToken)
      ),
      default => throw new \InvalidArgumentException('Either OTP ID or Challenge Token must be provided.'),
    };

    if ($otp === null) {
      throw OtpNotFoundException::create($command->otpId ?? $command->challengeToken ?? 'unknown');
    }

    try {
      $verified = $otp->verify($command->code);

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
  //#endregion
}
