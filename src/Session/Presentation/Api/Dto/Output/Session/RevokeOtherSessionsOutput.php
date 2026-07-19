<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Dto\Output\Session;

use ApiPlatform\Metadata\ApiProperty;
use Session\Presentation\Api\Serialization\SessionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO RevokeOtherSessionsOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RevokeOtherSessionsOutput
{
  // #region Properties
  /**
   * Property revokedCount.
   *
   * The number of sessions revoked by this call, excluding the caller's
   * current session. Zero when there was nothing else to revoke — the
   * operation is idempotent.
   */
  #[Groups(groups: [SessionSerializationGroup::REVOKE_OTHERS])]
  #[ApiProperty(
    description: 'Number of sessions revoked, excluding the caller\'s current session.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 2,
    openapiContext: [
      'type' => 'integer',
      'readOnly' => true,
    ],
  )]
  public int $revokedCount = 0;
  // #endregion
}
