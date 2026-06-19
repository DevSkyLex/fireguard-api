<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Domain\ValueObject\OrganizationSettings;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationSettingsOutput.
 *
 * Read representation of an organization's structured settings, grouping the
 * per-concern sub-sections.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSettingsOutput
{
  // #region Properties
  /**
   * Property notifications.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public OrganizationNotificationSettingsOutput $notifications;

  /**
   * Property regional.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public OrganizationRegionalSettingsOutput $regional;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the nested sub-sections with their defaults.
   *
   * @since 1.0.0
   */
  public function __construct()
  {
    $this->notifications = new OrganizationNotificationSettingsOutput();
    $this->regional = new OrganizationRegionalSettingsOutput();
  }
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * @static
   *
   * Builds the output from the domain settings value object.
   *
   * @since 1.0.0
   *
   * @param OrganizationSettings $settings the domain settings
   *
   * @return self the output instance
   */
  public static function fromDomain(OrganizationSettings $settings): self
  {
    $output = new self();
    $output->notifications = OrganizationNotificationSettingsOutput::fromDomain($settings->notifications);
    $output->regional = OrganizationRegionalSettingsOutput::fromDomain($settings->regional);

    return $output;
  }
  // #endregion
}
