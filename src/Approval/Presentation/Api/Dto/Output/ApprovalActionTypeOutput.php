<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Approval\Presentation\Api\Serialization\ApprovalSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO ApprovalActionTypeOutput.
 *
 * Reference catalog row for the settings UI (which action types can be
 * gated behind approval).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalActionTypeOutput
{
  // #region Properties
  /**
   * Property value.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $value = '';

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  #[Groups([ApprovalSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $label = '';
  // #endregion
}
