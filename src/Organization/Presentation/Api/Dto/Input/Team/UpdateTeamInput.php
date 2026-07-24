<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Team;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateTeamInput.
 *
 * Every field is optional: an omitted field leaves the current value
 * unchanged.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateTeamInput
{
  // #region Properties
  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 2, max: 80, minMessage: 'Team name must be at least 2 characters.', maxMessage: 'Team name cannot exceed 80 characters.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Team display name', required: false, example: 'Field crew A')]
  public ?string $name = null;

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 500, maxMessage: 'Team description cannot exceed 500 characters.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Team description', required: false, example: 'Handles rooftop equipment inspections')]
  public ?string $description = null;
  // #endregion
}
