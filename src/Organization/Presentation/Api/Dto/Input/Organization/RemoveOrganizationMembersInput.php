<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO RemoveOrganizationMembersInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RemoveOrganizationMembersInput
{
  // #region Properties
  /**
   * @var list<string>
   */
  #[Assert\Count(min: 1, minMessage: 'At least one member ID is required.')]
  #[Assert\All([
    new Assert\Uuid(message: 'Each member ID must be a valid UUID.'),
  ])]
  /**
   * Property memberIds.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Member IDs to remove in one batch', required: true)]
  public array $memberIds = [];
  // #endregion
}
