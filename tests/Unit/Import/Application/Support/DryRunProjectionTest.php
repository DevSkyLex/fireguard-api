<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\Support;

use Facility\Application\Contract\Hierarchy\FacilityHierarchyNode;
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
    self::assertSame([], $projection->facilityPendingCodeIds());
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

    $projection->recordFacilityWouldCreate('HQ', new FacilityHierarchyNode('site-1', 'site', null));
    $projection->recordFacilityWouldCreate(null, new FacilityHierarchyNode('building-1', 'building', 'site-1'));
    $projection->recordFacilityWouldCreate('ANNEX', new FacilityHierarchyNode('floor-1', 'floor', 'building-1'));

    self::assertSame(3, $projection->facilityCount());
    self::assertSame(['HQ' => 'site-1', 'ANNEX' => 'floor-1'], $projection->facilityPendingCodeIds());
    self::assertSame('building', $projection->facilityHierarchy()[1]->type);
    self::assertSame('site-1', $projection->facilityHierarchy()[1]->parentFacilityId);
  }

  #[Test]
  public function itNeverDuplicatesTheSameCodeTwice(): void
  {
    $projection = new DryRunProjection();

    $projection->recordFacilityWouldCreate('HQ', new FacilityHierarchyNode('first-site', 'site', null));
    $projection->recordFacilityWouldCreate('HQ', new FacilityHierarchyNode('second-site', 'site', null));

    self::assertSame(2, $projection->facilityCount());
    self::assertSame(['HQ' => 'first-site'], $projection->facilityPendingCodeIds());
    self::assertCount(2, $projection->facilityHierarchy());
  }

  #[Test]
  public function itReconstructsStableIdsPerJobAndOriginalRowNumber(): void
  {
    $jobId = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';
    $id = DryRunProjection::simulatedFacilityId($jobId, 2);

    self::assertSame('5dbef548-562c-5a34-8265-12b2266ee685', $id);
    self::assertSame($id, DryRunProjection::simulatedFacilityId($jobId, 2));
    self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id);
    self::assertNotSame($id, DryRunProjection::simulatedFacilityId($jobId, 3));
    self::assertNotSame($id, DryRunProjection::simulatedFacilityId('018f0b68-6758-7a12-8a1d-3f0d97f65a02', 2));
  }

  #[Test]
  public function itKeepsConfirmedQuotaCountWithoutInventingAnUnavailableParentNode(): void
  {
    $projection = new DryRunProjection();
    $projection->recordFacilityWouldCreate('STALE', null);

    self::assertSame(1, $projection->facilityCount());
    self::assertSame([], $projection->facilityPendingCodeIds());
    self::assertSame([], $projection->facilityHierarchy());
  }
}
