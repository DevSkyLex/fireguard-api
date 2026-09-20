<?php

declare(strict_types=1);

namespace Workload\Domain\ValueObject;

/**
 * Value object TaskAllocation.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TaskAllocation
{
  /**
   * @since 1.0.0
   *
   * @param array<string, int> $dailyMinutes
   * @param ?string $unallocatedReason explicit reason why the remaining work could not be distributed
   */
  public function __construct(public array $dailyMinutes = [], public ?string $unallocatedReason = null)
  {
  }
}
