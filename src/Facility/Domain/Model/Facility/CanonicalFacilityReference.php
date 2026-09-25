<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Facility;

use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityRecordStatus};

/** Persisted identity, publication state and parent reference. */
final readonly class CanonicalFacilityReference
{
  public function __construct(
    public FacilityId $id,
    public FacilityOrganizationId $organizationId,
    public FacilityRecordStatus $recordStatus,
    public ?string $interventionId,
    public ?string $parentFacilityId,
  ) {
  }
}
