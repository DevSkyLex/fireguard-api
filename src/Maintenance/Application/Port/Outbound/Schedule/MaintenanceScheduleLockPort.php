<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Outbound\Schedule;

/** Serialize all writers of one equipment's derived schedule in main. */
interface MaintenanceScheduleLockPort
{
  /**
   * @template T
   *
   * @param list<array{organizationId: string, equipmentId: string}> $scopes ordered lock scopes
   * @param callable(): T $work work after all locks are held
   *
   * @return T result
   */
  public function synchronizedBatch(array $scopes, callable $work): mixed;

  /** @template T
   * @param callable(): T $work
   *
   * @return T
   */
  public function synchronized(string $organizationId, string $equipmentId, callable $work): mixed;
}
