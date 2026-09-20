<?php

declare(strict_types=1);

namespace Workload\Application\Contract\Capacity;

/**
 * Contract CapacityWeekView.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CapacityWeekView
{
  /**
   * @since 1.0.0
   *
   * @param string $id stable resource identifier, retained across idempotent retries
   * @param string $scopeId organization identifier for defaults, or member identifier for an individual override
   * @param string $effectiveOn first local date on which this capacity version applies
   * @param list<int> $minutes
   */
  public function __construct(public string $id, public string $scopeId, public string $effectiveOn, public array $minutes)
  {
  }
}
