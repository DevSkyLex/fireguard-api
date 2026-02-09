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
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'eyJ...',
    openapiContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
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
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: '123456',
    openapiContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
  )]
  public string $code;

  // #endregion
}
