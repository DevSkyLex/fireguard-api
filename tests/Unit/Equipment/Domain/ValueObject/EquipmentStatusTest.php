<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\ValueObject;

use Equipment\Domain\ValueObject\EquipmentStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentStatusTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentStatus::class)]
final class EquipmentStatusTest extends TestCase
{
  #[Test]
  public function itExposesBackingValues(): void
  {
    self::assertSame('in_stock', EquipmentStatus::IN_STOCK->value);
    self::assertSame('operational', EquipmentStatus::OPERATIONAL->value);
    self::assertSame('under_maintenance', EquipmentStatus::UNDER_MAINTENANCE->value);
    self::assertSame('decommissioned', EquipmentStatus::DECOMMISSIONED->value);
  }

  #[Test]
  public function itDetectsOperational(): void
  {
    self::assertTrue(EquipmentStatus::OPERATIONAL->isOperational());
    self::assertFalse(EquipmentStatus::IN_STOCK->isOperational());
  }

  #[Test]
  public function itDetectsDecommissioned(): void
  {
    self::assertTrue(EquipmentStatus::DECOMMISSIONED->isDecommissioned());
    self::assertFalse(EquipmentStatus::OPERATIONAL->isDecommissioned());
  }

  #[Test]
  public function itLabelsEveryCase(): void
  {
    self::assertSame('In stock', EquipmentStatus::IN_STOCK->label());
    self::assertSame('Operational', EquipmentStatus::OPERATIONAL->label());
    self::assertSame('Under maintenance', EquipmentStatus::UNDER_MAINTENANCE->label());
    self::assertSame('Decommissioned', EquipmentStatus::DECOMMISSIONED->label());
  }
}
