<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Input\Facility;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO DuplicateFacilitySubtreeInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DuplicateFacilitySubtreeInput
{
  // #region Properties
  /**
   * Property name.
   *
   * Optional name for the copy's root. Defaults to "{original} (copy)" when
   * omitted.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 2, max: 120, minMessage: 'Facility name must be between 2 and 120 characters.', maxMessage: 'Facility name must be between 2 and 120 characters.')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Name for the copy\'s root. Defaults to "{original} (copy)" when omitted.', required: false, example: 'Site A (copy)')]
  public ?string $name = null;

  /**
   * Property parentFacilityId.
   *
   * Optional target parent for the copy's root. Defaults to the source
   * facility's own parent when omitted.
   *
   * @since 1.0.0
   */
  #[Assert\Uuid(message: 'Parent facility ID must be a valid UUID.')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Target parent facility identifier for the copy\'s root. Defaults to the source facility\'s own parent when omitted.', required: false, example: '550e8400-e29b-41d4-a716-446655440001')]
  public ?string $parentFacilityId = null;
  // #endregion
}
