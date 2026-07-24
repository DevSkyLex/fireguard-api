<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\ValueObject;

use Equipment\Domain\ValueObject\EquipmentId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test EquipmentIdTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentId::class)]
final class EquipmentIdTest extends TestCase
{
  private const string VALID_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function itCreatesFromValidUuid(): void
  {
    $id = EquipmentId::fromString(self::VALID_UUID);

    self::assertSame(self::VALID_UUID, $id->value);
    self::assertSame(self::VALID_UUID, (string) $id);
  }

  #[Test]
  public function itComparesEquality(): void
  {
    $a = EquipmentId::fromString(self::VALID_UUID);
    $b = EquipmentId::fromString(self::VALID_UUID);

    self::assertTrue($a->equals($b));
  }

  #[Test]
  public function itRejectsInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    EquipmentId::fromString('not-a-uuid');
  }
}
