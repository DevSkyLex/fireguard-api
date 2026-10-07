<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Port\Outbound\Rate;

use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;

/** Append-only hourly-rate persistence and stable request identity. */
interface MaintenanceRateStorePort
{
  /**
   * @template T
   *
   * @param callable():T $work
   *
   * @return T
   */
  public function synchronized(string $organizationId, callable $work): mixed;

  public function findByClientId(string $organizationId, string $clientId): ?MaintenanceRateSnapshot;

  public function findByEffectiveDate(string $organizationId, string $memberId, string $effectiveFrom): ?MaintenanceRateSnapshot;

  public function append(string $organizationId, string $clientId, MaintenanceRateSnapshot $rate): void;

  /**
   * @return list<MaintenanceRateSnapshot>
   */
  public function list(string $organizationId, int $limit, int $offset, ?string $memberId = null): array;

  public function count(string $organizationId, ?string $memberId = null): int;
}
