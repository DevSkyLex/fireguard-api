<?php

declare(strict_types=1);

namespace Workload\Application\Contract\Planning;

/**
 * WorkloadPlanningSnapshot.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkloadPlanningSnapshot
{
  /**
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param list<string> $memberIds
   * @param \Workload\Application\Contract\Projection\WorkloadProjectionSnapshot $projection complete server-calculated workload projection
   */
  public function __construct(public string $organizationId, public array $memberIds, public \Workload\Application\Contract\Projection\WorkloadProjectionSnapshot $projection)
  {
  }
}
