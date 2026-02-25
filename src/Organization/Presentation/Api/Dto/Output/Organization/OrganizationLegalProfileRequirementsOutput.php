<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationLegalProfileRequirementsOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationLegalProfileRequirementsOutput
{
  // #endregion

  // #region Properties
  /**
   * Property registrationNumber.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public OrganizationLegalFieldRequirementOutput $registrationNumber;

  /**
   * Property vatNumber.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public OrganizationLegalFieldRequirementOutput $vatNumber;

  // #region Constructor
  public function __construct()
  {
    $this->registrationNumber = new OrganizationLegalFieldRequirementOutput();
    $this->vatNumber = new OrganizationLegalFieldRequirementOutput();
  }
  // #endregion
}
