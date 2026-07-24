<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\UseCase\Query\GetComplianceOverview;

use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Application\UseCase\Query\GetComplianceOverview\GetComplianceOverviewResult;
use Compliance\Domain\ValueObject\ComplianceStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test GetComplianceOverviewResultTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetComplianceOverviewResult::class)]
final class GetComplianceOverviewResultTest extends TestCase
{
  #[Test]
  public function testConstructorExposesItsProperties(): void
  {
    $facility = new FacilityComplianceView(
      facilityId: 'facility-1',
      name: 'Site A',
      type: 'site',
      parentFacilityId: null,
      path: 'Site A',
      status: ComplianceStatus::COMPLIANT,
      totalEquipmentCount: 3,
      activeEquipmentCount: 3,
      upToDateEquipmentCount: 3,
      dueSoonEquipmentCount: 0,
      overdueEquipmentCount: 0,
      unscheduledEquipmentCount: 0,
      openLowNonConformityCount: 0,
      openMediumNonConformityCount: 0,
      openHighNonConformityCount: 0,
      openCriticalNonConformityCount: 0,
      lastInspectionAt: null,
    );

    $totals = [
      'totalEquipmentCount' => 3,
      'activeEquipmentCount' => 3,
      'upToDateEquipmentCount' => 3,
      'dueSoonEquipmentCount' => 0,
      'overdueEquipmentCount' => 0,
      'unscheduledEquipmentCount' => 0,
      'trackedEquipmentCount' => 3,
      'complianceRate' => 100.0,
      'openLowNonConformityCount' => 0,
      'openMediumNonConformityCount' => 0,
      'openHighNonConformityCount' => 0,
      'openCriticalNonConformityCount' => 0,
    ];

    $result = new GetComplianceOverviewResult(
      generatedAt: '2026-07-24T10:00:00+00:00',
      organizationStatus: ComplianceStatus::COMPLIANT,
      totals: $totals,
      facilities: [$facility],
    );

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame('2026-07-24T10:00:00+00:00', $result->generatedAt);
    self::assertSame(ComplianceStatus::COMPLIANT, $result->organizationStatus);
    self::assertSame($totals, $result->totals);
    self::assertSame([$facility], $result->facilities);
  }
}
