<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Inspection;

use DateTimeImmutable;
use Inspection\Domain\ValueObject\{InspectionResult, InspectionStatus};

/** Finding and lifecycle state, with restored text kept as persisted. */
final readonly class InspectionFinding
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures inspection outcome, lifecycle, performed time, notes, and signature.
   *
   * @access public
   *
   * @param InspectionResult $result recorded outcome of the inspection
   * @param InspectionStatus $status lifecycle status of the inspection
   * @param DateTimeImmutable $performedAt time the inspection was performed
   * @param ?string $notes optional inspection notes
   * @param ?string $signature optional inspector signature data
   *
   * @return void
   */
  public function __construct(
    public InspectionResult $result,
    public InspectionStatus $status,
    public DateTimeImmutable $performedAt,
    public ?string $notes = null,
    public ?string $signature = null,
  ) {
  }
  // #endregion
}
