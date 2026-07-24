<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Presentation\Api\Factory;

use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Domain\ValueObject\ComplianceStatus;
use Compliance\Presentation\Api\Factory\ComplianceSummaryOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ComplianceSummaryOutputFactoryTest.
 *
 * Covers that `trackedEquipmentCount` (previously computed and dropped) and
 * `complianceRate` are serialized onto both the per-facility rows and the
 * `totals` rollup, additively, without disturbing the existing fields.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ComplianceSummaryOutputFactory::class)]
final class ComplianceSummaryOutputFactoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFromOrganizationRegisterExposesTrackedEquipmentCountAndComplianceRateOnFacilityRow(): void
  {
    $factory = new ComplianceSummaryOutputFactory();

    $facility = $this->makeView(upToDate: 3, dueSoon: 0, overdue: 1);

    $output = $factory->fromOrganizationRegister(
      organizationId: 'org-1',
      generatedAt: '2026-01-01T00:00:00+00:00',
      organizationStatus: ComplianceStatus::NON_COMPLIANT->value,
      totals: [
        'totalEquipmentCount' => 4,
        'activeEquipmentCount' => 4,
        'upToDateEquipmentCount' => 3,
        'dueSoonEquipmentCount' => 0,
        'overdueEquipmentCount' => 1,
        'unscheduledEquipmentCount' => 0,
        'trackedEquipmentCount' => 4,
        'complianceRate' => 75.0,
        'openLowNonConformityCount' => 0,
        'openMediumNonConformityCount' => 0,
        'openHighNonConformityCount' => 0,
        'openCriticalNonConformityCount' => 0,
      ],
      facilities: [$facility],
    );

    self::assertSame(4, $output->totals['trackedEquipmentCount']);
    self::assertSame(75.0, $output->totals['complianceRate']);
    self::assertSame(4, $output->facilities[0]['trackedEquipmentCount']);
    self::assertSame(75.0, $output->facilities[0]['complianceRate']);
    // Existing fields keep their exact names/semantics (purely additive contract change).
    self::assertSame(3, $output->facilities[0]['upToDateEquipmentCount']);
    self::assertSame(1, $output->facilities[0]['overdueEquipmentCount']);
  }

  #[Test]
  public function testFromFacilityRegisterReturnsNullRateWhenNoEquipmentIsTracked(): void
  {
    $factory = new ComplianceSummaryOutputFactory();

    $facility = $this->makeView(upToDate: 0, dueSoon: 0, overdue: 0);

    $output = $factory->fromFacilityRegister(
      organizationId: 'org-1',
      generatedAt: '2026-01-01T00:00:00+00:00',
      facility: $facility,
    );

    self::assertSame(0, $output->totals['trackedEquipmentCount']);
    self::assertNull($output->totals['complianceRate']);
    self::assertSame(0, $output->facilities[0]['trackedEquipmentCount']);
    self::assertNull($output->facilities[0]['complianceRate']);
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
