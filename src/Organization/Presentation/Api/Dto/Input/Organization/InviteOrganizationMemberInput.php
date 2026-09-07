<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO InviteOrganizationMemberInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InviteOrganizationMemberInput
{
  // #region Properties
  /**
   * @since 1.2.0 Optional prepared onboarding receipt; both fields travel together.
   */
  #[Assert\Uuid]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  public ?string $onboardingSessionId = null;

  /**
   * @since 1.2.0 Stable item identity from the preparation response.
   */
  #[Assert\Regex('/^[a-zA-Z0-9_-]{1,80}$/D')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  public ?string $onboardingItemKey = null;

  /**
   * Property email.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Email is required.')]
  #[Assert\Email(message: 'Email must be a valid email address.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Email to invite in Organization', required: true, example: 'member@example.com')]
  public string $email = '';

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
  #[ApiProperty(description: 'Initial role IDs for this invitation', required: false, example: ['550e8400-e29b-41d4-a716-446655440001'])]
  public array $roleIds = [];
  // #endregion
}
