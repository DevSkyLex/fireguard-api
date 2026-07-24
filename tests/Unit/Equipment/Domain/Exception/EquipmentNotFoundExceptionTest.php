<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Exception;

use Equipment\Domain\Exception\EquipmentNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_contains;

/**
 * Test EquipmentNotFoundExceptionTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentNotFoundException::class)]
final class EquipmentNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function itBuildsWithId(): void
  {
    $exception = EquipmentNotFoundException::withId('equip-1');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertTrue(str_contains($exception->getMessage(), 'equip-1'));
  }
}
