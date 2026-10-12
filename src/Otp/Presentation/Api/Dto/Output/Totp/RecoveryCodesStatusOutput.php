<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Output\Totp;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Class RecoveryCodesStatusOutput
 *
 * Exposes availability metadata without codes or stored digests.
 *
 * @category DTO
 */
final readonly class RecoveryCodesStatusOutput
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param int $remainingCount remaining single-use proofs
   * @param ?string $generatedAt current lot ISO 8601 generation time
   *
   * @return void
   */
  public function __construct(
    #[Groups(['recovery_codes:read'])]
    #[ApiProperty(description: 'Number of unconsumed recovery codes.', writable: false)]
    public int $remainingCount,
    #[Groups(['recovery_codes:read'])]
    #[ApiProperty(description: 'Generation timestamp for the current lot, or null when none exists.', writable: false, openapiContext: ['type' => 'string', 'format' => 'date-time', 'nullable' => true])]
    public ?string $generatedAt,
  ) {
  }
  // #endregion
}
