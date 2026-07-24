<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Domain\ValueObject\OrganizationRegionalSettings;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateOrganizationRegionalInput.
 *
 * Partial organization regional and formatting payload. Every field is optional;
 * only the provided fields are applied on top of the current settings. Values are
 * constrained to the same catalog as the domain value object.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateOrganizationRegionalInput
{
  // #region Properties
  /**
   * Property timezone.
   *
   * @since 1.0.0
   */
  #[Assert\Timezone]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'IANA timezone identifier', required: false, example: 'Europe/Paris')]
  public ?string $timezone = null;

  /**
   * Property locale.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: OrganizationRegionalSettings::ALLOWED_LOCALES)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Display locale', required: false, example: 'fr-FR')]
  public ?string $locale = null;

  /**
   * Property dateFormat.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: OrganizationRegionalSettings::ALLOWED_DATE_FORMATS)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Date format pattern', required: false, example: 'dd/MM/yyyy')]
  public ?string $dateFormat = null;

  /**
   * Property firstDayOfWeek.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: OrganizationRegionalSettings::ALLOWED_FIRST_DAYS)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'First day of the week', required: false, example: 'monday')]
  public ?string $firstDayOfWeek = null;

  /**
   * Property measurementSystem.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: OrganizationRegionalSettings::ALLOWED_MEASUREMENT_SYSTEMS)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Measurement system', required: false, example: 'metric')]
  public ?string $measurementSystem = null;
  // #endregion
}
