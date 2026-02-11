<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO AssignOrganizationRoleInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssignOrganizationRoleInput
{
  // #region Properties
  /**
   * Property roleId.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Role ID is required.')]
  #[Assert\Uuid(message: 'Role ID must be a valid UUID.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Role ID to assign', required: true, example: '550e8400-e29b-41d4-a716-446655440000')]
  public string $roleId = '';
  // #endregion
}
