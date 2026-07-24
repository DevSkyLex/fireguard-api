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

  /**
   * Property compliance.
   *
   * @since 1.1.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public OrganizationComplianceSettingsOutput $compliance;

  /**
   * Property automation.
   *
   * @since 1.1.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public OrganizationAutomationSettingsOutput $automation;

  /**
   * Property approval.
   *
   * @since 1.2.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public OrganizationApprovalSettingsOutput $approval;

  /**
   * Property assistant.
   *
   * @since 1.3.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public OrganizationAssistantSettingsOutput $assistant;
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
    $this->compliance = new OrganizationComplianceSettingsOutput();
    $this->automation = new OrganizationAutomationSettingsOutput();
    $this->approval = new OrganizationApprovalSettingsOutput();
    $this->assistant = new OrganizationAssistantSettingsOutput();
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
    $output->compliance = OrganizationComplianceSettingsOutput::fromDomain($settings->compliance);
    $output->automation = OrganizationAutomationSettingsOutput::fromDomain($settings->automation);
    $output->approval = OrganizationApprovalSettingsOutput::fromDomain($settings->approval);
    $output->assistant = OrganizationAssistantSettingsOutput::fromDomain($settings->assistant);

    return $output;
  }
  // #endregion
}
