<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Persistence\Doctrine\Lock;

use Doctrine\DBAL\Connection;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleLockPort;

/** Transaction-scoped lock also protects the first insert, before a row exists. */
final readonly class MaintenanceScheduleLockAdapter implements MaintenanceScheduleLockPort
{
  public function __construct(private Connection $connection)
  {
  }

  public function synchronized(string $organizationId, string $equipmentId, callable $work): mixed
  {
    return $this->connection->transactional(function () use ($organizationId, $equipmentId, $work): mixed {
      $this->connection->executeStatement('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', [
        'key' => 'maintenance.schedule.' . $organizationId . '.' . $equipmentId,
      ]);

      return $work();
    });
  }
}
