<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\Contract;

use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Domain\ValueObject\ComplianceStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityComplianceViewTest.
 *
 * Covers the compliance-rate formula (`upToDateEquipmentCount /
 * trackedEquipmentCount`, expressed as a 0.0-100.0 percentage rounded to 1
 * decimal) and its zero-denominator `null` case — the single source of
 * truth shared by the per-facility row and the organization rollup.
 *
 * @category Contract Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityComplianceView::class)]
final class FacilityComplianceViewTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testComplianceRateComputesPercentageRoundedToOneDecimal(): void
  {
    $view = $this->makeView(upToDate: 2, dueSoon: 0, overdue: 1);

    // trackedEquipmentCount = 2 + 0 + 1 = 3; rate = 2/3*100 = 66.666... -> 66.7
    self::assertSame(3, $view->trackedEquipmentCount());
    self::assertSame(66.7, $view->complianceRate());
  }

  #[Test]
  public function testComplianceRateIsOneHundredWhenEveryTrackedItemIsUpToDate(): void
  {
    $view = $this->makeView(upToDate: 5, dueSoon: 0, overdue: 0);

    self::assertSame(5, $view->trackedEquipmentCount());
    self::assertSame(100.0, $view->complianceRate());
  }

  #[Test]
  public function testComplianceRateIsNullNotZeroWhenNoEquipmentIsTracked(): void
  {
    $view = $this->makeView(upToDate: 0, dueSoon: 0, overdue: 0);

    self::assertSame(0, $view->trackedEquipmentCount());
    self::assertNull($view->complianceRate());
  }

  #[Test]
  public function testComputeComplianceRateStaticFormulaMatchesInstanceMethod(): void
  {
    self::assertSame(50.0, FacilityComplianceView::computeComplianceRate(5, 10));
    self::assertNull(FacilityComplianceView::computeComplianceRate(0, 0));
  }

  private function makeView(int $upToDate, int $dueSoon, int $overdue): FacilityComplianceView
  {
    return new FacilityComplianceView(
      facilityId: 'facility-1',
      name: 'Site A',
      type: 'site',
      parentFacilityId: null,
      path: 'Site A',
      status: ComplianceStatus::COMPLIANT,
      totalEquipmentCount: $upToDate + $dueSoon + $overdue,
      activeEquipmentCount: $upToDate + $dueSoon + $overdue,
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
  // #endregion
}
