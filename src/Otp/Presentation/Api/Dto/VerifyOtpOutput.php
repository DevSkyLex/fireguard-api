<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto;

/**
 * DTO VerifyOtpOutput
 * @final
 *
 * Output DTO for OTP verification result.
 *
 * @category DTO
 * @package Otp\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class VerifyOtpOutput
{
  //#region Properties
  /**
   * Property success
   *
   * Whether verification was successful.
   *
   * @var bool
   */
  public bool $success;

  /**
   * Property attemptsRemaining
   *
   * Remaining verification attempts.
   *
   * @var int
   */
  public int $attemptsRemaining;

  /**
   * Property error
   *
   * Error message if failed.
   *
   * @var string|null
   */
  public ?string $error = null;
  //#endregion
}
