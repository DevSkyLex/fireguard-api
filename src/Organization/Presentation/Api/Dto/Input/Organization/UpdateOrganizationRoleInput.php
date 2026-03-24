<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateOrganizationRoleInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateOrganizationRoleInput
{
  // #region Properties
  /**
   * @var list<string>
   */
  #[Assert\NotBlank(message: 'At least one permission is required.')]
  #[Assert\All([
    new Assert\Type(type: 'string'),
    new Assert\NotBlank(message: 'Permission cannot be blank.'),
  ])]
  /**
   * Property permissions.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Role permissions', required: true, example: ['organization.read', 'organization.members.read'])]
  public array $permissions = [];

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 500, maxMessage: 'Role description cannot exceed 500 characters.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Role description', required: false, example: 'Can inspect equipment')]
  public ?string $description = null;
  // #endregion
}
