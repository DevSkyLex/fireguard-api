<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Exception;

use Facility\Domain\Exception\FacilityNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test FacilityNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityNotFoundException::class)]
final class FacilityNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function testWithIdBuildsMessage(): void
  {
    $exception = FacilityNotFoundException::withId('fac-42');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Facility with ID "fac-42" not found.', $exception->getMessage());
  }
}
