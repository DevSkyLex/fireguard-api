<?php

declare(strict_types=1);

namespace Workload\Application\Contract\Planning;

/**
 * WorkloadTaskProposal.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkloadTaskProposal
{
  /**
   * @since 1.0.0
   *
   * @param string $taskId intervention work-item identifier
   * @param ?string $memberId proposed assignee; null explicitly removes the assignment
   * @param ?int $remainingMinutes explicit remaining effort in whole minutes; never derived from actual time
   * @param ?string $startsOn inclusive organization-local start date
   * @param ?string $endsOn inclusive organization-local end date
   */
  public function __construct(public string $taskId, public ?string $memberId, public ?int $remainingMinutes, public ?string $startsOn, public ?string $endsOn)
  {
  }
}
