<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\ValueObject;

use Intervention\Domain\ValueObject\InterventionType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionType.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionType::class)]
final class InterventionTypeTest extends TestCase
{
  #[Test]
  public function testBackingValuesAreStable(): void
  {
    self::assertSame('site_setup', InterventionType::SITE_SETUP->value);
    self::assertSame('inventory', InterventionType::INVENTORY->value);
    self::assertSame('inspection_campaign', InterventionType::INSPECTION_CAMPAIGN->value);
    self::assertSame('preventive_maintenance', InterventionType::PREVENTIVE_MAINTENANCE->value);
    self::assertSame('corrective_maintenance', InterventionType::CORRECTIVE_MAINTENANCE->value);
  }

  #[Test]
  public function testFromResolvesTheBackingValue(): void
  {
    self::assertSame(InterventionType::INVENTORY, InterventionType::from('inventory'));
  }

  #[Test]
  public function testExposesExactlyFiveCases(): void
  {
    self::assertSame([
      InterventionType::SITE_SETUP,
      InterventionType::INVENTORY,
      InterventionType::INSPECTION_CAMPAIGN,
      InterventionType::PREVENTIVE_MAINTENANCE,
      InterventionType::CORRECTIVE_MAINTENANCE,
    ], InterventionType::cases());
  }
}
