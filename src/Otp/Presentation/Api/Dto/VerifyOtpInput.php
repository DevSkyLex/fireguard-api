<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO VerifyOtpInput
 * @final
 *
 * Input DTO for OTP verification.
 *
 * @category DTO
 * @package Otp\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class VerifyOtpInput
{
  //#region Properties
  /**
   * Property code
   *
   * The verification code.
   *
   * @var string
   */
  #[Assert\NotBlank]
  #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Code must be 6 digits.')]
  public string $code;
  //#endregion
}
