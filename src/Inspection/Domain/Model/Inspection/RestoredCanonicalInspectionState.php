<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Inspection;

use Inspection\Domain\ValueObject\{InspectionRecordStatus, InspectionResult, InspectionStatus};

/** The lifecycle and revision persisted for the canonical inspection surface. */
final readonly class RestoredCanonicalInspectionState
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Restores the canonical record status, inspection outcome, revision, and persisted text fields.
   *
   * @access public
   *
   * @param InspectionRecordStatus $recordStatus publication state of the canonical persistence record
   * @param ?string $interventionId linked intervention identifier, when present
   * @param InspectionStatus $status inspection lifecycle status
   * @param InspectionResult $result recorded inspection outcome
   * @param ?string $notes persisted notes, when present
   * @param ?string $signature persisted signature data, when present
   * @param int $revision optimistic-lock revision persisted for the inspection
   *
   * @return void
   */
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
  // #endregion
}
