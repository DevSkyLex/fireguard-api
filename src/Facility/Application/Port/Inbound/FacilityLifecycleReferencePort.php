<?php

declare(strict_types=1);

namespace Facility\Application\Port\Inbound;

use InvalidArgumentException;

/**
 * Port FacilityLifecycleReferencePort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityLifecycleReferencePort
{
  // #region Methods
  /**
   * Validates a new facility relation in its final publication context.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization
   * @param string $facilityId the referenced facility
   * @param ?string $draftInterventionId the owning intervention only for a draft resource
   * @param ?string $publishingInterventionId the intervention currently being published
   */
  public function assertReference(string $organizationId, string $facilityId, ?string $draftInterventionId = null, ?string $publishingInterventionId = null): void;

  /**
   * Method assertRetainedReference.
   *
   * Validates an existing historical relation without requiring an active facility.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization
   * @param string $facilityId the retained facility
   * @param ?string $draftInterventionId the owning intervention only for a draft resource
   * @param ?string $publishingInterventionId the intervention currently being published
   *
   * @return void
   *
   * @throws InvalidArgumentException when the reference is missing or outside its scope
   */
  public function assertRetainedReference(string $organizationId, string $facilityId, ?string $draftInterventionId = null, ?string $publishingInterventionId = null): void;
  // #endregion
}
