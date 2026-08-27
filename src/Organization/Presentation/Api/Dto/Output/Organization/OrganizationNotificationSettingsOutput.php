<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Domain\ValueObject\OrganizationNotificationSettings;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationNotificationSettingsOutput.
 *
 * Read representation of an organization's notification policy.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationNotificationSettingsOutput
{
  // #region Properties
  /**
   * Property emailEnabled.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $emailEnabled = true;

  /**
   * Property inAppEnabled.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $inAppEnabled = true;

  /**
   * Property interventionPublished.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $interventionPublished = true;

  /**
   * Property interventionAssigned.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $interventionAssigned = true;

  /**
   * Property inspectionDue.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $inspectionDue = true;

  /**
   * Property nonConformityOpened.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $nonConformityOpened = true;

  /**
   * Property nonConformitySlaBreached.
   *
   * @since 1.1.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $nonConformitySlaBreached = true;

  /**
   * Property memberInvited.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $memberInvited = true;
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * @static
   *
   * Builds the output from the domain notification settings value object.
   *
   * @since 1.0.0
   *
   * @param OrganizationNotificationSettings $settings the domain notification settings
   *
   * @return self the output instance
   */
  public static function fromDomain(OrganizationNotificationSettings $settings): self
  {
    $output = new self();
    $output->emailEnabled = $settings->emailEnabled;
    $output->inAppEnabled = $settings->inAppEnabled;
    $output->interventionPublished = $settings->interventionPublished;
    $output->interventionAssigned = $settings->interventionAssigned;
    $output->inspectionDue = $settings->inspectionDue;
    $output->nonConformityOpened = $settings->nonConformityOpened;
    $output->nonConformitySlaBreached = $settings->nonConformitySlaBreached;
    $output->memberInvited = $settings->memberInvited;

    return $output;
  }
  // #endregion
}
