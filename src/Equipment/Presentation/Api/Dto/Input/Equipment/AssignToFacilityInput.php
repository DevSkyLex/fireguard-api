<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Input\Equipment;

use ApiPlatform\Metadata\ApiProperty;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO AssignToFacilityInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssignToFacilityInput
{
  // #region Properties
  /**
   * Property facilityId.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Facility ID is required.')]
  #[Assert\Uuid(message: 'Facility ID must be a valid UUID.')]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Target facility identifier', required: true, example: '550e8400-e29b-41d4-a716-446655440001')]
  public string $facilityId = '';

  /**
   * Property installedAt.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(allowNull: true)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional installation date (ISO 8601)', required: false, example: '2024-01-15T09:00:00+00:00')]
  public ?string $installedAt = null;
  // #endregion
}
