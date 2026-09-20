<?php

declare(strict_types=1);

namespace Workload\Application\Contract\Projection;

/**
 * Contract UnallocatedWorkView.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UnallocatedWorkView
{
  /**
   * @since 1.0.0
   *
   * @param string $taskId intervention work-item identifier
   * @param string $interventionId owning intervention identifier
   * @param string $label human-readable task label for the contribution
   * @param ?string $memberId assigned member, or null for work that still needs assignment
   * @param ?int $remainingMinutes explicit remaining effort in whole minutes; never derived from actual time
   * @param ?string $startsOn inclusive organization-local start date
   * @param ?string $endsOn inclusive organization-local end date
   * @param string $commitment demand classification: draft, committed, or no future demand
   * @param string $reason reason the task cannot be included in a complete daily allocation
   */
  public function __construct(
    public string $taskId,
    public string $interventionId,
    public string $label,
    public ?string $memberId,
    public ?int $remainingMinutes,
    public ?string $startsOn,
    public ?string $endsOn,
    public string $commitment,
    public string $reason,
  ) {
  }
}
