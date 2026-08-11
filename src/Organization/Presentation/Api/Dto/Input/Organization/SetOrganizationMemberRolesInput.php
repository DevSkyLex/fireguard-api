<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO SetOrganizationMemberRolesInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SetOrganizationMemberRolesInput
{
  // #region Properties
  /**
   * Property roleIds.
   *
   * The complete set of role IDs the member must hold afterward — a full
   * replacement, not a delta. An empty array clears every role assignment.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Assert\All([
    new Assert\Uuid(message: 'Each role ID must be a valid UUID.'),
  ])]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'The complete set of role IDs the member must hold afterward', required: true)]
  public array $roleIds = [];
  // #endregion
}
