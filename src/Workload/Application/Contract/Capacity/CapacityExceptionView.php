<?php

declare(strict_types=1);

namespace Workload\Application\Contract\Capacity;

/**
 * Contract CapacityExceptionView.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CapacityExceptionView
{
  /**
   * @since 1.0.0
   *
   * @param string $id stable resource identifier, retained across idempotent retries
   * @param string $memberId organization member whose work or capacity is represented
   * @param string $startsOn inclusive organization-local start date
   * @param string $endsOn inclusive organization-local end date
   * @param int $minutes duration in whole minutes
   */
  public function __construct(public string $id, public string $memberId, public string $startsOn, public string $endsOn, public int $minutes)
  {
  }
}
