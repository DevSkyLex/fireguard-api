<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Domain\ValueObject\OrganizationRegionalSettings;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationRegionalSettingsOutput.
 *
 * Read representation of an organization's regional and formatting settings.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationRegionalSettingsOutput
{
  // #region Properties
  /**
   * Property timezone.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $timezone = 'UTC';

  /**
   * Property locale.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $locale = 'en-US';

  /**
   * Property dateFormat.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $dateFormat = 'yyyy-MM-dd';

  /**
   * Property firstDayOfWeek.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $firstDayOfWeek = 'monday';

  /**
   * Property measurementSystem.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $measurementSystem = 'metric';
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * @static
   *
   * Builds the output from the domain regional settings value object.
   *
   * @since 1.0.0
   *
   * @param OrganizationRegionalSettings $settings the domain regional settings
   *
   * @return self the output instance
   */
  public static function fromDomain(OrganizationRegionalSettings $settings): self
  {
    $output = new self();
    $output->timezone = $settings->timezone;
    $output->locale = $settings->locale;
    $output->dateFormat = $settings->dateFormat;
    $output->firstDayOfWeek = $settings->firstDayOfWeek;
    $output->measurementSystem = $settings->measurementSystem;

    return $output;
  }
  // #endregion
}
