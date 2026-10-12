<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Input\Totp;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class GenerateRecoveryCodesInput
 *
 * Accepts current active TOTP possession proof; recovery codes cannot regenerate themselves.
 *
 * @category DTO
 */
final class GenerateRecoveryCodesInput
{
  // #region Properties
  /**
   * Property code
   *
   * Current authenticator proof, never read back in a response.
   */
  #[Groups(['recovery_codes:write'])]
  #[Assert\NotBlank]
  #[Assert\Regex(pattern: '/\A[0-9]{6}\z/')]
  #[ApiProperty(description: 'Current six-digit code from the active authenticator.', readable: false, writable: true, required: true, openapiContext: ['type' => 'string', 'writeOnly' => true, 'pattern' => '^[0-9]{6}$'])]
  public string $code;
  // #endregion
}
