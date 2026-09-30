<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

/**
 * Interface ChecklistLockPort
 *
 * Serializes checklist operations that must not overlap for the same organization and checklist.
 *
 * @category Port
 */
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
