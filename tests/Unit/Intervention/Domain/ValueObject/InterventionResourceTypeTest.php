<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\ValueObject;

use Intervention\Domain\ValueObject\InterventionResourceType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionResourceType.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionResourceType::class)]
final class InterventionResourceTypeTest extends TestCase
{
  #[Test]
  public function testBackingValuesAreStable(): void
  {
    self::assertSame('facility', InterventionResourceType::FACILITY->value);
    self::assertSame('equipment', InterventionResourceType::EQUIPMENT->value);
    self::assertSame('inspection', InterventionResourceType::INSPECTION->value);
  }

  #[Test]
  public function testFromResolvesTheBackingValue(): void
  {
    self::assertSame(InterventionResourceType::EQUIPMENT, InterventionResourceType::from('equipment'));
  }

  #[Test]
  public function testExposesExactlyThreeCases(): void
  {
    self::assertCount(3, InterventionResourceType::cases());
  }
}
