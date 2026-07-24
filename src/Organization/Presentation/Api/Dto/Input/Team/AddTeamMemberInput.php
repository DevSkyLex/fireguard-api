<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Team;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO AddTeamMemberInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AddTeamMemberInput
{
  // #region Properties
  /**
   * Property memberId.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Member ID is required.')]
  #[Assert\Uuid(message: 'Member ID must be a valid UUID.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Organization member ID to add', required: true, example: '550e8400-e29b-41d4-a716-446655440000')]
  public string $memberId = '';

  /**
   * Property role.
   *
   * Free-form membership label (e.g. "lead"). NOT an RBAC role.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 50, maxMessage: 'Membership role label cannot exceed 50 characters.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Free-form membership label (not an RBAC role)', required: false, example: 'lead')]
  public ?string $role = null;
  // #endregion
}
