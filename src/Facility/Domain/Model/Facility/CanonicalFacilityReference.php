<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Facility;

use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityRecordStatus};

/** Persisted identity, publication state and parent reference. */
final readonly class CanonicalFacilityReference
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the canonical facility identity, organization, publication state, and parent reference.
   *
   * @access public
   *
   * @param FacilityId $id canonical facility identifier
   * @param FacilityOrganizationId $organizationId organization owning the facility
   * @param FacilityRecordStatus $recordStatus publication state of the persisted facility record
   * @param ?string $interventionId linked intervention identifier, when present
   * @param ?string $parentFacilityId parent facility identifier, when the facility is nested
   *
   * @return void
   */
  public function __construct(
    public FacilityId $id,
    public FacilityOrganizationId $organizationId,
    public FacilityRecordStatus $recordStatus,
    public ?string $interventionId,
    public ?string $parentFacilityId,
  ) {
  }
  // #endregion
}
