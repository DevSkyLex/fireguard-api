<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO MfaVerifyInput
 * @final
 *
 * Input for MFA verification.
 *
 * @category Input DTO
 * @package Auth\Presentation\Dto\Input
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MfaVerifyInput
{
  //#region Properties
  /**
   * Property preAuthToken
   *
   * The Pre-Auth Token received during login.
   *
   * @var string
   */
  #[Assert\NotBlank]
  #[ApiProperty(
    description: 'The Pre-Auth Token received from the login response.',
    example: 'eyJ...',
  )]
  public string $preAuthToken;

  /**
   * Property code
   *
   * The OTP code.
   *
   * @var string
   */
  #[Assert\NotBlank]
  #[ApiProperty(
    description: 'The OTP code received by the user.',
    example: '123456',
  )]
  public string $code;
  //#endregion
}
