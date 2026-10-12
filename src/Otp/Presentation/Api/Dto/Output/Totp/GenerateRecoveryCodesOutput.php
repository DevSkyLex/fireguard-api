<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Output\Totp;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Class GenerateRecoveryCodesOutput
 *
 * Delivers the plaintext lot once after its digests commit successfully.
 *
 * @category DTO
 */
final readonly class GenerateRecoveryCodesOutput
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param list<string> $codes ten newly generated recovery codes
   * @param string $generatedAt ISO 8601 generation timestamp
   *
   * @return void
   */
  public function __construct(
    #[Groups(['recovery_codes:read'])]
    #[ApiProperty(description: 'Ten one-time recovery codes. Save them now; metadata reads never return them.', writable: false, openapiContext: ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 10, 'maxItems' => 10])]
    public array $codes,
    #[Groups(['recovery_codes:read'])]
    #[ApiProperty(description: 'Generation timestamp for the new lot.', writable: false, openapiContext: ['type' => 'string', 'format' => 'date-time'])]
    public string $generatedAt,
  ) {
  }
  // #endregion
}
