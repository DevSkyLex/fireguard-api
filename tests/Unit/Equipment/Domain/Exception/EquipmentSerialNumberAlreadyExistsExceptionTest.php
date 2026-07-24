<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Exception;

use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_contains;

/**
 * Test EquipmentSerialNumberAlreadyExistsExceptionTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentSerialNumberAlreadyExistsException::class)]
final class EquipmentSerialNumberAlreadyExistsExceptionTest extends TestCase
{
  #[Test]
  public function itBuildsWithSerialNumber(): void
  {
    $exception = EquipmentSerialNumberAlreadyExistsException::withSerialNumber('SN-123');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertTrue(str_contains($exception->getMessage(), 'SN-123'));
  }
}
