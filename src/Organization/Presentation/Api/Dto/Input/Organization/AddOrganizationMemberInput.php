<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO AddOrganizationMemberInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AddOrganizationMemberInput
{
  // #region Properties
  /**
   * Property userId.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'User ID is required.')]
  #[Assert\Uuid(message: 'User ID must be a valid UUID.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'User ID to add in Organization', required: true, example: '550e8400-e29b-41d4-a716-446655440000')]
  public string $userId = '';

  /**
   * @var list<string>
   */
  #[Assert\All([
    new Assert\Uuid(message: 'Each role ID must be a valid UUID.'),
  ])]
  /**
   * Property roleIds.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Initial role IDs for this member', required: false, example: ['550e8400-e29b-41d4-a716-446655440001'])]
  public array $roleIds = [];
  // #endregion
}
