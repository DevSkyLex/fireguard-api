<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateOrganizationSettingsInput.
 *
 * Partial general & branding payload. Every field is optional; only the
 * provided fields are applied. Send an empty `description` to clear it.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateOrganizationSettingsInput
{
  // #region Properties
  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 2, max: 120)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Organization display name', required: false, example: 'Fireguard Paris')]
  public ?string $name = null;

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

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 2000)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Free-text organization description', required: false)]
  public ?string $description = null;

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the organization is active', required: false)]
  public ?bool $isActive = null;

  /**
   * Property notifications.
   *
   * @since 1.0.0
   */
  #[Assert\Valid]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Partial organization notification policy', required: false)]
  public ?UpdateOrganizationNotificationsInput $notifications = null;

  /**
   * Property regional.
   *
   * @since 1.0.0
   */
  #[Assert\Valid]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Partial organization regional and formatting settings', required: false)]
  public ?UpdateOrganizationRegionalInput $regional = null;
  // #endregion
}
