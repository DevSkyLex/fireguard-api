<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Exception;

use Facility\Domain\Exception\FacilityHasActiveDependentsException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_contains;

/**
 * Test FacilityHasActiveDependentsException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityHasActiveDependentsException::class)]
final class FacilityHasActiveDependentsExceptionTest extends TestCase
{
  #[Test]
  public function testWithActiveChildFacilities(): void
  {
    $exception = FacilityHasActiveDependentsException::withActiveChildFacilities('fac-1');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertTrue(str_contains($exception->getMessage(), 'fac-1'));
    self::assertTrue(str_contains($exception->getMessage(), 'active child facilities'));
  }

  #[Test]
  public function testWithActiveEquipment(): void
  {
    $exception = FacilityHasActiveDependentsException::withActiveEquipment('fac-2');

    self::assertTrue(str_contains($exception->getMessage(), 'fac-2'));
    self::assertTrue(str_contains($exception->getMessage(), 'active equipment'));
  }

  #[Test]
  public function testWithActiveInspections(): void
  {
    $exception = FacilityHasActiveDependentsException::withActiveInspections('fac-3');

    self::assertTrue(str_contains($exception->getMessage(), 'fac-3'));
    self::assertTrue(str_contains($exception->getMessage(), 'in-progress inspections'));
  }
}
