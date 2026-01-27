<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Input\Auth;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO MfaVerifyInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MfaVerifyInput
{
  // #region Properties
  /**
   * Property preAuthToken.
   *
   * The Pre-Auth Token received during login.
   */
  #[Assert\NotBlank]
  #[ApiProperty(
    description: 'The Pre-Auth Token received from the login response.',
    example: 'eyJ...',
  )]
  public string $preAuthToken;

  /**
   * Property code.
   *
   * The OTP code.
   */
  #[Assert\NotBlank]
  #[ApiProperty(
    description: 'The OTP code received by the user.',
    example: '123456',
  )]
  public string $code;

  // #endregion
}
