<?php

declare(strict_types=1);

namespace Workload\Application\Port\Inbound;

/**
 * Port WorkloadCoordinationPort. Requires a main transaction owned by the caller.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface WorkloadCoordinationPort
{
  /**
   * Acquires transaction-scoped organization and stably ordered member locks.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param list<string> $memberIds stable ordering is enforced by the adapter
   * @param bool $organizationWide whether to acquire exclusive organization-wide capacity coordination
   *
   * @return void completes without returning a value
   */
  public function acquire(string $organizationId, array $memberIds, bool $organizationWide = false): void;
}
