<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Checklist;

use Doctrine\DBAL\Connection;
use Inspection\Application\Port\Outbound\ChecklistLockPort;

final readonly class ChecklistLockAdapter implements ChecklistLockPort
{
  public function __construct(private Connection $connection)
  {
  }

  public function withLock(string $organizationId, ?string $checklistId, callable $work): mixed
  {
    return $this->connection->transactional(function () use ($organizationId, $checklistId, $work): mixed {
      if (null !== $checklistId) {
        $this->connection->executeQuery(
          'SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))',
          ['key' => 'inspection.checklist.' . $organizationId . '.' . $checklistId],
        );
      }

      return $work();
    });
  }
}
