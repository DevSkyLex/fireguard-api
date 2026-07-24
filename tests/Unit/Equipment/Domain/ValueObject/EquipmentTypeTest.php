<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\ValueObject;

use Equipment\Domain\ValueObject\EquipmentType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function count;

/**
 * Test EquipmentTypeTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentType::class)]
final class EquipmentTypeTest extends TestCase
{
  #[Test]
  public function itReturnsAllBackingValues(): void
  {
    $values = EquipmentType::values();

    self::assertSame(count(EquipmentType::cases()), count($values));
    self::assertContains('fire_extinguisher', $values);
    self::assertContains('other', $values);
  }

  #[Test]
  public function itLabelsEveryCase(): void
  {
    foreach (EquipmentType::cases() as $case) {
      self::assertNotSame('', $case->label());
    }

    self::assertSame('Fire Extinguisher', EquipmentType::FIRE_EXTINGUISHER->label());
    self::assertSame('Other', EquipmentType::OTHER->label());
  }
}
