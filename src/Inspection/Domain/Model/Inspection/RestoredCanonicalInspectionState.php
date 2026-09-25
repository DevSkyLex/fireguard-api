<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Inspection;

use Inspection\Domain\ValueObject\{InspectionRecordStatus, InspectionResult, InspectionStatus};

/** The lifecycle and revision persisted for the canonical inspection surface. */
final readonly class RestoredCanonicalInspectionState
{
  public function __construct(
    public InspectionRecordStatus $recordStatus,
    public ?string $interventionId,
    public InspectionStatus $status,
    public InspectionResult $result,
    public ?string $notes,
    public ?string $signature,
    public int $revision,
  ) {
  }
}
