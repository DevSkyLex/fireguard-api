<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\Exception\FacilityHierarchyException;
use Facility\Domain\ValueObject\{FacilityHierarchyPolicy, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityHierarchyPolicyTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityHierarchyPolicy::class)]
final class FacilityHierarchyPolicyTest extends TestCase
{
  // #region Tests
  /**
   * Method testTheCompleteTaxonomyIncludesOutdoorZonesAndOnlySiteRoots.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testTheCompleteTaxonomyIncludesOutdoorZonesAndOnlySiteRoots(): void
  {
    foreach (FacilityType::cases() as $type) {
      self::assertSame(FacilityType::SITE === $type, FacilityHierarchyPolicy::allowsParent($type, null));
      foreach (FacilityType::cases() as $parent) {
        $expected = match ($type) {
          FacilityType::SITE => false,
          FacilityType::BUILDING => FacilityType::SITE === $parent,
          FacilityType::FLOOR => FacilityType::BUILDING === $parent,
          FacilityType::ZONE, FacilityType::AREA => true,
        };
        self::assertSame($expected, FacilityHierarchyPolicy::allowsParent($type, $parent), $type->value . '/' . $parent->value);
      }
    }
  }

  /**
   * Method testAFloorUnderAnOutdoorZoneIsRejected.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testAFloorUnderAnOutdoorZoneIsRejected(): void
  {
    $this->expectException(FacilityHierarchyException::class);
    FacilityHierarchyPolicy::assertParent(FacilityType::FLOOR, FacilityType::ZONE);
  }
  // #endregion
}
