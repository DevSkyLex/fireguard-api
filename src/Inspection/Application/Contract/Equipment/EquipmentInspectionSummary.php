<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Equipment;

/**
 * Contract EquipmentInspectionSummary.
 *
 * Published inspection facts and open findings for one scoped equipment.
 *
 * @category Contract
 */
final readonly class EquipmentInspectionSummary
{
  /**
   * Method __construct.
   *
   * @param string $equipmentId the scoped equipment
   * @param int $openAnomalies the number of open or in-progress findings
   * @param array{low: int, medium: int, high: int, critical: int} $bySeverity open findings by severity
   * @param ?string $lastInspectionId the most recent closed published inspection
   * @param ?string $lastInspectionPerformedAt its actual execution instant
   * @param ?string $lastInspectionResult its recorded pass, fail or partial result
   */
  public function __construct(
    public string $equipmentId,
    public int $openAnomalies,
    public array $bySeverity,
    public ?string $lastInspectionId,
    public ?string $lastInspectionPerformedAt,
    public ?string $lastInspectionResult,
  ) {
  }
}
