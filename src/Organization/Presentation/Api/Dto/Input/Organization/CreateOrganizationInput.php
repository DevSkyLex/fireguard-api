<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateOrganizationInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateOrganizationInput
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
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Organization name is required.')]
  #[Assert\Length(min: 2, max: 120)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Organization display name', required: true, example: 'Fireguard Paris')]
  public string $name = '';

  /**
   * Property slug.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 3, max: 120)]
  #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Slug must use lowercase letters, numbers and dashes.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Public organization slug', required: false, example: 'fireguard-paris')]
  public ?string $slug = null;
  // #endregion
}
