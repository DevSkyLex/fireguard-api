<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\ValueObject\FacilityType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityType.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityType::class)]
final class FacilityTypeTest extends TestCase
{
  #[Test]
  public function testCaseValues(): void
  {
    self::assertSame('site', FacilityType::SITE->value);
    self::assertSame('building', FacilityType::BUILDING->value);
    self::assertSame('floor', FacilityType::FLOOR->value);
    self::assertSame('zone', FacilityType::ZONE->value);
    self::assertSame('area', FacilityType::AREA->value);
  }

  #[Test]
  public function testValuesReturnsEveryCaseValueInOrder(): void
  {
    self::assertSame(
      ['site', 'building', 'floor', 'zone', 'area'],
      FacilityType::values(),
    );
  }

  #[Test]
  public function testFromReconstructsCase(): void
  {
    self::assertSame(FacilityType::ZONE, FacilityType::from('zone'));
  }
}
