<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Input\Challenge;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO VerifyOtpInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class VerifyOtpInput
{
  // #region Properties
  /**
   * Property code.
   *
   * The verification code.
   */
  #[Assert\NotBlank]
  public string $code;
  // #endregion
}
