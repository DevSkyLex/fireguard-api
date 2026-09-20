<?php

declare(strict_types=1);

namespace Workload\Application\Contract\Capacity;

/**
 * CapacityConfigurationView.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CapacityConfigurationView
{
  /**
   * @since 1.0.0
   *
   * @param list<CapacityWeekView> $weeks
   * @param list<CapacityExceptionView> $exceptions
   */
  public function __construct(public array $weeks, public array $exceptions)
  {
  }
}
