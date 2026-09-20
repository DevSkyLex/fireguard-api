<?php

declare(strict_types=1);

namespace Workload\Application\Port\Inbound;

use Intervention\Application\Contract\Workload\InterventionWorkContribution;
use Workload\Application\Contract\Projection\WorkloadProjectionSnapshot;

/**
 * Port WorkloadProjectionPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface WorkloadProjectionPort
{
  /**
   * Projects daily actual and remaining demand while keeping drafts and unknown work explicit.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param string $from inclusive first local date in the requested period
   * @param string $to inclusive last local date in the requested period
   * @param ?list<string> $memberIds null means organization-wide, including unassigned work
   * @param list<InterventionWorkContribution> $replacements simulated replacements, never persisted
   *
   * @return WorkloadProjectionSnapshot daily projection and fingerprint of the relevant input data
   */
  public function project(string $organizationId, string $from, string $to, ?array $memberIds = null, array $replacements = []): WorkloadProjectionSnapshot;
}
