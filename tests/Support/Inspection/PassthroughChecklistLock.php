<?php

declare(strict_types=1);

namespace Tests\Support\Inspection;

use Inspection\Application\Port\Outbound\ChecklistLockPort;

final readonly class PassthroughChecklistLock implements ChecklistLockPort
{
  public function withLock(string $organizationId, ?string $checklistId, callable $work): mixed
  {
    return $work();
  }
}
