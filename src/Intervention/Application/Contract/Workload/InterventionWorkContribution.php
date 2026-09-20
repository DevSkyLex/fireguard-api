<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Workload;

/**
 * Contract InterventionWorkContribution.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionWorkContribution
{
  /**
   * @since 1.0.0
   *
   * @param string $taskId intervention work-item identifier
   * @param string $interventionId owning intervention identifier
   * @param string $label human-readable task label for the contribution
   * @param ?string $memberId current task assignee, or null for unassigned work
   * @param ?int $remainingMinutes explicit remaining effort in whole minutes; never derived from actual time
   * @param ?string $startsOn inclusive organization-local start date
   * @param ?string $endsOn inclusive organization-local end date
   * @param string $commitment demand classification: draft, committed, or no future demand
   * @param int $revision revision of this resource, used independently of other aggregates
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
    public int $revision,
  ) {
  }
}
