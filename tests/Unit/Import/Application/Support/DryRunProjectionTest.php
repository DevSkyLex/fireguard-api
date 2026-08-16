<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\Support;

use Import\Application\Support\DryRunProjection;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test DryRunProjectionTest.
 *
 * @category Support Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DryRunProjection::class)]
final class DryRunProjectionTest extends TestCase
{
  #[Test]
  public function itStartsAtZeroWithNoPendingCodes(): void
  {
    $projection = new DryRunProjection();

    self::assertSame(0, $projection->equipmentCount());
    self::assertSame(0, $projection->facilityCount());
    self::assertSame([], $projection->facilityPendingCodes());
  }

  #[Test]
  public function itIncrementsTheEquipmentCountOnEachWouldCreate(): void
  {
    $projection = new DryRunProjection();

    $projection->recordEquipmentWouldCreate();
    $projection->recordEquipmentWouldCreate();

    self::assertSame(2, $projection->equipmentCount());
  }

  #[Test]
  public function itIncrementsTheFacilityCountAndCollectsTheCode(): void
  {
    $projection = new DryRunProjection();

    $projection->recordFacilityWouldCreate('HQ');
    $projection->recordFacilityWouldCreate(null);
    $projection->recordFacilityWouldCreate('ANNEX');

    self::assertSame(3, $projection->facilityCount());
    self::assertSame(['HQ', 'ANNEX'], $projection->facilityPendingCodes());
  }

  #[Test]
  public function itNeverDuplicatesTheSameCodeTwice(): void
  {
    $projection = new DryRunProjection();

    $projection->recordFacilityWouldCreate('HQ');
    $projection->recordFacilityWouldCreate('HQ');

    self::assertSame(2, $projection->facilityCount());
    self::assertSame(['HQ'], $projection->facilityPendingCodes());
  }
}
