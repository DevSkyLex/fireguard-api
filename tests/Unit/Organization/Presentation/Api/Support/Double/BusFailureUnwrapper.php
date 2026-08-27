<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Support\Double;

use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Throwable;

/**
 * Test double exposing UnwrapsOrganizationBusFailures.
 *
 * @category Test Double
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class BusFailureUnwrapper
{
  use UnwrapsOrganizationBusFailures;

  /**
   * Finds the first wrapped exception of the expected class.
   *
   * @param class-string<Throwable> $expectedClass the expected exception class
   */
  public function find(Throwable $exception, string $expectedClass): ?Throwable
  {
    return $this->findWrappedException($exception, $expectedClass);
  }
}
