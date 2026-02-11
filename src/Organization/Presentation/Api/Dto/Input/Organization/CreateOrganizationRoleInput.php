<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateOrganizationRoleInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateOrganizationRoleInput
{
  #[Assert\NotBlank(message: 'Role name is required.')]
  #[Assert\Regex(
    pattern: '/^[a-z0-9_]{3,50}$/',
    message: 'Role name must be 3-50 chars, lowercase alphanumeric or underscore.',
    // #region Properties
    /**
     * Property name.
     *
     * @since 1.0.0
     */
  )]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Role name', required: true, example: 'inspector')]
  public string $name = '';

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
  // #endregion
}
