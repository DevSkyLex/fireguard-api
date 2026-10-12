<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Input\Totp;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO DisableTotpInput.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DisableTotpInput
{
  // #region Properties
  /**
   * Property proofType
   *
   * Recovery disable requires an unconsumed code independent of any code used for login.
   */
  #[Assert\Choice(choices: ['totp', 'recovery_code'])]
  #[ApiProperty(description: 'Current authenticator or unconsumed recovery code proof.', openapiContext: ['type' => 'string', 'enum' => ['totp', 'recovery_code'], 'default' => 'totp'])]
  public string $proofType = 'totp';

  /**
   * Property code.
   *
   * The current TOTP code, proving possession of the authenticator app
   * before disabling TOTP.
   */
  #[Assert\NotBlank]
  #[ApiProperty(
    description: 'A current TOTP code or unconsumed recovery proof matching proofType.',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: '123456',
    openapiContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
  )]
  public string $code;
  // #endregion
}
