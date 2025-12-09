<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto;

use DateTimeImmutable;

/**
 * DTO OtpOutput
 * @final
 *
 * Output DTO for OTP responses.
 *
 * @category DTO
 * @package Otp\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OtpOutput
{
  //#region Properties
  /**
   * Property id
   *
   * The OTP ID.
   *
   * @var string
   */
  public string $id;

  /**
   * Property status
   *
   * The OTP status (pending, verified, expired, failed).
   *
   * @var string
   */
  public string $status;

  /**
   * Property maskedRecipient
   *
   * The masked recipient.
   *
   * @var string
   */
  public string $maskedRecipient;

  /**
   * Property expiresAt
   *
   * When the OTP expires.
   *
   * @var DateTimeImmutable
   */
  public DateTimeImmutable $expiresAt;

  /**
   * Property attemptsRemaining
   *
   * Remaining verification attempts.
   *
   * @var int
   */
  public int $attemptsRemaining;
  //#endregion
}
