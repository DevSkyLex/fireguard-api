<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Input\Facility;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO MoveFacilityInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MoveFacilityInput
{
  // #region Properties
  /**
   * Property parentFacilityId.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(allowNull: true, message: 'Parent facility ID cannot be blank.')]
  #[Assert\Uuid(message: 'Parent facility ID must be a valid UUID.')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Target parent facility identifier. Field is required; use null to detach from parent.', required: true, example: '550e8400-e29b-41d4-a716-446655440001')]
  public ?string $parentFacilityId = null;
  // #endregion
}
