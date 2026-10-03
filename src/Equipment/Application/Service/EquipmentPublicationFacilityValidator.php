<?php

declare(strict_types=1);

namespace Equipment\Application\Service;

use Equipment\Application\Port\Outbound\FacilityValidationPort;
use Facility\Application\Port\Inbound\FacilityLifecycleReferencePort;

/**
 * Service EquipmentPublicationFacilityValidator.
 *
 * Validates retained facility references when equipment is published.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentPublicationFacilityValidator
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityValidationPort $facilities the policy for active assignments
   * @param ?FacilityLifecycleReferencePort $retainedReferences the policy for terminal historical assignments
   *
   * @return void no return value
   */
  public function __construct(private FacilityValidationPort $facilities, private ?FacilityLifecycleReferencePort $retainedReferences = null)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method assertReference.
   *
   * Preserves historical terminal targets while validating active equipment.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization
   * @param ?string $facilityId the retained facility target
   * @param string $status the final equipment lifecycle status
   * @param ?string $publishingInterventionId the drafts being published atomically
   *
   * @return void no return value
   */
  public function assertReference(string $organizationId, ?string $facilityId, string $status, ?string $publishingInterventionId = null): void
  {
    if (null === $facilityId) {
      return;
    }
    if ('decommissioned' === $status && null !== $this->retainedReferences) {
      $this->retainedReferences->assertRetainedReference($organizationId, $facilityId, null, $publishingInterventionId);

      return;
    }
    $this->facilities->assertFacilityIsAssignable($facilityId, $organizationId, null, $publishingInterventionId);
  }
  // #endregion
}
