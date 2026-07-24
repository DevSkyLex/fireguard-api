<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\Service;

use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Application\Service\{ComplianceRegisterAggregator, ComplianceStatusPolicy, FacilityTreeBuilder};
use Compliance\Domain\ValueObject\ComplianceStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityTreeBuilderTest.
 *
 * Covers pure in-memory tree assembly from an already-batched, flat
 * {@see FacilityComplianceView} list: nesting, the `unassigned` exclusion,
 * orphan-parent handling, and that the equipment count/verdict/rate are
 * reused as-is (never recomputed).
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityTreeBuilder::class)]
final class FacilityTreeBuilderTest extends TestCase
{
  #[Test]
  public function testBuildAssemblesNestedHierarchyFromFlatViews(): void
  {
    $site = $this->makeView(id: 'site-1', name: 'Site A', type: 'site', parentFacilityId: null);
    $building = $this->makeView(id: 'building-1', name: 'Building A', type: 'building', parentFacilityId: 'site-1');
    $floor = $this->makeView(id: 'floor-1', name: 'Floor 1', type: 'floor', parentFacilityId: 'building-1');

    // Order-independent: siblings/children can arrive in any order from the directory port.
    $tree = FacilityTreeBuilder::build([$floor, $site, $building]);

    self::assertCount(1, $tree);
    self::assertSame('site-1', $tree[0]->id);
    self::assertCount(1, $tree[0]->children);
    self::assertSame('building-1', $tree[0]->children[0]->id);
    self::assertCount(1, $tree[0]->children[0]->children);
    self::assertSame('floor-1', $tree[0]->children[0]->children[0]->id);
    self::assertSame([], $tree[0]->children[0]->children[0]->children);
  }

  #[Test]
  public function testBuildExcludesUnassignedPseudoFacility(): void
  {
    $site = $this->makeView(id: 'site-1', name: 'Site A', type: 'site', parentFacilityId: null);
    $unassigned = $this->makeView(
      id: ComplianceRegisterAggregator::UNASSIGNED_FACILITY_KEY,
      name: 'Unassigned',
      type: ComplianceRegisterAggregator::UNASSIGNED_FACILITY_KEY,
      parentFacilityId: null,
    );

    $tree = FacilityTreeBuilder::build([$site, $unassigned]);

    self::assertCount(1, $tree);
    self::assertSame('site-1', $tree[0]->id);
  }

  #[Test]
  public function testBuildTreatsFacilityWithParentOutsideDirectoryAsRoot(): void
  {
    $orphan = $this->makeView(id: 'floor-1', name: 'Floor 1', type: 'floor', parentFacilityId: 'missing-building');

    $tree = FacilityTreeBuilder::build([$orphan]);

    self::assertCount(1, $tree);
    self::assertSame('floor-1', $tree[0]->id);
    self::assertSame([], $tree[0]->children);
  }

  #[Test]
  public function testBuildReturnsMultipleRootsForMultipleSites(): void
  {
    $siteA = $this->makeView(id: 'site-a', name: 'Site A', type: 'site', parentFacilityId: null);
    $siteB = $this->makeView(id: 'site-b', name: 'Site B', type: 'site', parentFacilityId: null);

    $tree = FacilityTreeBuilder::build([$siteA, $siteB]);

    self::assertCount(2, $tree);
  }

  #[Test]
  public function testBuildReusesEquipmentCountVerdictAndRateWithoutRecomputation(): void
  {
    $view = $this->makeView(
      id: 'site-1',
      name: 'Site A',
      type: 'site',
      parentFacilityId: null,
      totalEquipmentCount: 5,
      upToDate: 3,
      dueSoon: 0,
      overdue: 1,
    );

    $tree = FacilityTreeBuilder::build([$view]);

    self::assertSame(5, $tree[0]->equipmentCount);
    self::assertSame($view->status, $tree[0]->status);
    self::assertSame(ComplianceStatus::NON_COMPLIANT, $tree[0]->status);
    self::assertSame($view->complianceRate(), $tree[0]->complianceRate);
  }

  private function makeView(
    string $id,
    string $name,
    string $type,
    ?string $parentFacilityId,
    int $totalEquipmentCount = 0,
    int $upToDate = 0,
    int $dueSoon = 0,
    int $overdue = 0,
  ): FacilityComplianceView {
    return new FacilityComplianceView(
      facilityId: $id,
      name: $name,
      type: $type,
      parentFacilityId: $parentFacilityId,
      path: $name,
      status: ComplianceStatusPolicy::grade(
        overdueEquipmentCount: $overdue,
        dueSoonEquipmentCount: $dueSoon,
        trackedEquipmentCount: $upToDate + $dueSoon + $overdue,
        openHighNonConformityCount: 0,
        openCriticalNonConformityCount: 0,
      ),
      totalEquipmentCount: $totalEquipmentCount,
      activeEquipmentCount: $totalEquipmentCount,
      upToDateEquipmentCount: $upToDate,
      dueSoonEquipmentCount: $dueSoon,
      overdueEquipmentCount: $overdue,
      unscheduledEquipmentCount: 0,
      openLowNonConformityCount: 0,
      openMediumNonConformityCount: 0,
      openHighNonConformityCount: 0,
      openCriticalNonConformityCount: 0,
      lastInspectionAt: null,
    );
  }
}
