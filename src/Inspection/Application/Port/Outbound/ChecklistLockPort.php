<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

interface ChecklistLockPort
{
  /**
   * @template T
   *
   * @param callable(): T $work
   *
   * @return T
   */
  public function withLock(string $organizationId, ?string $checklistId, callable $work): mixed;
}
