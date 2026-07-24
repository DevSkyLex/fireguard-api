<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\UseCase\Query\GetFacilityCompliance;

use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Application\UseCase\Query\GetFacilityCompliance\GetFacilityComplianceResult;
use Compliance\Domain\ValueObject\ComplianceStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test GetFacilityComplianceResultTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityComplianceResult::class)]
final class GetFacilityComplianceResultTest extends TestCase
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
      status: ComplianceStatus::AT_RISK,
      totalEquipmentCount: 5,
      activeEquipmentCount: 5,
      upToDateEquipmentCount: 3,
      dueSoonEquipmentCount: 2,
      overdueEquipmentCount: 0,
      unscheduledEquipmentCount: 0,
      openLowNonConformityCount: 0,
      openMediumNonConformityCount: 0,
      openHighNonConformityCount: 1,
      openCriticalNonConformityCount: 0,
      lastInspectionAt: '2026-06-01T09:00:00+00:00',
    );

    $result = new GetFacilityComplianceResult(
      generatedAt: '2026-07-24T10:00:00+00:00',
      facility: $facility,
    );

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame('2026-07-24T10:00:00+00:00', $result->generatedAt);
    self::assertSame($facility, $result->facility);
  }
}
