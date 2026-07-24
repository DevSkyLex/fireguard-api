<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Exception;

use Equipment\Domain\Exception\EquipmentAlreadyDecommissionedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_contains;

/**
 * Test EquipmentAlreadyDecommissionedExceptionTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentAlreadyDecommissionedException::class)]
final class EquipmentAlreadyDecommissionedExceptionTest extends TestCase
{
  #[Test]
  public function itBuildsWithId(): void
  {
    $exception = EquipmentAlreadyDecommissionedException::withId('equip-9');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertTrue(str_contains($exception->getMessage(), 'equip-9'));
  }
}
