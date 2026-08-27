<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Input\Equipment;

use ApiPlatform\Metadata\ApiProperty;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO SetEquipmentPlanPositionInput.
 *
 * `attachmentId`, `x` and `y` are required together (to set or replace the
 * position), or all null (to clear it) — a PUT-with-null-to-clear shape
 * mirroring Facility's `SetFacilityPlanGeometryInput`. Pairwise completeness
 * is a handler concern — this DTO validates shape only.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SetEquipmentPlanPositionInput
{
  // #region Properties
  /**
   * Property attachmentId.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(allowNull: true, message: 'Attachment ID cannot be blank.')]
  #[Assert\Uuid(message: 'Attachment ID must be a valid UUID.')]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Floor plan attachment identifier the position is bound to. Required together with x/y; use null on all three to clear.', required: true, example: '550e8400-e29b-41d4-a716-446655440001')]
  public ?string $attachmentId = null;

  /**
   * Property x.
   *
   * @since 1.0.0
   */
  #[Assert\Range(min: 0, max: 1, notInRangeMessage: 'X must be normalized between 0 and 1.')]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Normalized x coordinate in [0, 1]. Required together with attachmentId/y; use null on all three to clear.', required: true, example: 0.42)]
  public ?float $x = null;

  /**
   * Property y.
   *
   * @since 1.0.0
   */
  #[Assert\Range(min: 0, max: 1, notInRangeMessage: 'Y must be normalized between 0 and 1.')]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Normalized y coordinate in [0, 1]. Required together with attachmentId/x; use null on all three to clear.', required: true, example: 0.17)]
  public ?float $y = null;
  // #endregion
}
