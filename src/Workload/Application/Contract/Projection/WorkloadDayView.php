<?php

declare(strict_types=1);

namespace Workload\Application\Contract\Projection;

/**
 * Contract WorkloadDayView.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkloadDayView
{
  /**
   * @since 1.0.0
   *
   * @param string $date organization-local calendar date
   * @param ?int $capacityMinutes available minutes; null is unknown and zero means unavailable
   * @param int $actualMinutes recorded work, independent of remaining effort
   * @param int $remainingMinutes explicit remaining effort in whole minutes; never derived from actual time
   * @param int $draftMinutes tentative demand from draft interventions
   * @param ?int $overloadMinutes committed daily excess; null when capacity is unknown
   * @param ?float $utilizationPercent daily utilization, or null when capacity cannot be divided
   * @param string $completeness calculation quality: complete, partial, or unavailable
   * @param string $availability availability classification that preserves incomplete data
   * @param list<array{taskId: string, kind: string, minutes: int, entryId: ?string, interventionId?: ?string, label?: ?string}> $contributions
   */
  public function __construct(
    public string $date,
    public ?int $capacityMinutes,
    public int $actualMinutes,
    public int $remainingMinutes,
    public int $draftMinutes,
    public ?int $overloadMinutes,
    public ?float $utilizationPercent,
    public string $completeness,
    public string $availability,
    public array $contributions,
  ) {
  }
}
