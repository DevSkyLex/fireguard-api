<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Domain\Catalog\OrganizationComplianceDefaults;
use Organization\Domain\ValueObject\OrganizationComplianceSettings;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationComplianceSettingsOutput.
 *
 * Read representation of an organization's compliance policy. Maps carry the
 * EFFECTIVE values (catalog defaults overlaid with the organization's
 * customizations); the `customized*` lists tell the client which keys are
 * organization-specific so provenance never has to be guessed.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationComplianceSettingsOutput
{
  // #region Properties
  /**
   * Property nonConformitySlaDays.
   *
   * @since 1.0.0
   *
   * @var array<string, int>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Effective non-conformity SLA in days per severity')]
  public array $nonConformitySlaDays = OrganizationComplianceDefaults::NON_CONFORMITY_SLA_DAYS;

  /**
   * Property inspectionPeriodicityDefaults.
   *
   * @since 1.0.0
   *
   * @var array<string, string>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Effective inspection periodicity (ISO-8601 duration) per equipment type')]
  public array $inspectionPeriodicityDefaults = OrganizationComplianceDefaults::INSPECTION_PERIODICITIES;

  /**
   * Property reminderWindowDays.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Days before a due date during which reminders fire')]
  public int $reminderWindowDays = OrganizationComplianceDefaults::REMINDER_WINDOW_DAYS;

  /**
   * Property customizedSlaSeverities.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Severities whose SLA differs from the catalog default')]
  public array $customizedSlaSeverities = [];

  /**
   * Property customizedPeriodicityTypes.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Equipment types whose periodicity differs from the catalog default')]
  public array $customizedPeriodicityTypes = [];
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * @static
   *
   * Builds the output from the domain compliance settings value object.
   *
   * @since 1.0.0
   *
   * @param OrganizationComplianceSettings $compliance the domain compliance settings
   *
   * @return self the output instance
   */
  public static function fromDomain(OrganizationComplianceSettings $compliance): self
  {
    $output = new self();
    $output->nonConformitySlaDays = $compliance->effectiveNonConformitySlaDays();
    $output->inspectionPeriodicityDefaults = $compliance->effectiveInspectionPeriodicities();
    $output->reminderWindowDays = $compliance->reminderWindowDays;
    $output->customizedSlaSeverities = $compliance->customizedSeverities();
    $output->customizedPeriodicityTypes = $compliance->customizedEquipmentTypes();

    return $output;
  }
  // #endregion
}
