<?php

declare(strict_types=1);

namespace Workload\Application\Contract\Projection;

/**
 * Contract MemberWorkloadView.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MemberWorkloadView
{
  /**
   * @since 1.0.0
   *
   * @param string $memberId organization member whose work or capacity is represented
   * @param list<WorkloadDayView> $days
   * @param list<UnallocatedWorkView> $unallocated
   * @param ?string $displayName member label resolved within the organization
   */
  public function __construct(public string $memberId, public array $days, public array $unallocated, public ?string $displayName = null)
  {
  }
}
