<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\GetOtpStatus;

use Otp\Application\Port\Outbound\OtpRepositoryPort;
use Otp\Domain\Exception\OtpNotFoundException;
use Otp\Domain\ValueObject\OtpId;

/**
 * Handler GetOtpStatusHandler
 * @final
 *
 * Handles OTP status query.
 *
 * @category Handler
 * @package Otp\Application\UseCase\Query\GetOtpStatus
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOtpStatusHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * GetOtpStatusHandler class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param OtpRepositoryPort $otpRepository The OTP repository.
   */
  public function __construct(
    private readonly OtpRepositoryPort $otpRepository,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the GetOtpStatusQuery.
   *
   * @access public
   * @since 1.0.0
   *
   * @param GetOtpStatusQuery $query The query.
   *
   * @return GetOtpStatusResult The result.
   *
   * @throws OtpNotFoundException If OTP not found.
   */
  public function __invoke(GetOtpStatusQuery $query): GetOtpStatusResult
  {
    $otp = $this->otpRepository->findById(
      id: new OtpId(value: $query->otpId)
    );

    if ($otp === null) throw OtpNotFoundException::create(
      id: $query->otpId
    );

    return new GetOtpStatusResult(
      status: $otp->status(),
      expiresAt: $otp->expiresAt(),
      attemptsRemaining: $otp->attemptsRemaining(),
      maskedRecipient: $otp->maskedRecipient(),
      purpose: $otp->purpose()->value,
      channel: $otp->channel()->value,
      recipient: $otp->recipient(),
      createdAt: $otp->createdAt(),
    );
  }
  //#endregion
}
