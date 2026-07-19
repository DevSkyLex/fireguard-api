<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Domain\ValueObject\OrganizationAutomationSettings;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationAutomationSettingsOutput.
 *
 * Read representation of an organization's automation toggles.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAutomationSettingsOutput
{
  // #region Properties
  /**
   * Property autoCreateInterventionOnCriticalNc.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Automatically create a draft corrective intervention when a critical non-conformity is recorded')]
  public bool $autoCreateInterventionOnCriticalNc = false;
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * @static
   *
   * Builds the output from the domain automation settings value object.
   *
   * @since 1.0.0
   *
   * @param OrganizationAutomationSettings $automation the domain automation settings
   *
   * @return self the output instance
   */
  public static function fromDomain(OrganizationAutomationSettings $automation): self
  {
    $output = new self();
    $output->autoCreateInterventionOnCriticalNc = $automation->autoCreateInterventionOnCriticalNc;

    return $output;
  }
  // #endregion
}
