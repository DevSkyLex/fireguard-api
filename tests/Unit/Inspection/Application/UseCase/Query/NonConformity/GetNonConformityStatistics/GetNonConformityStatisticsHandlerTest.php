<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\NonConformity\GetNonConformityStatistics;

use DateTimeImmutable;
use Inspection\Application\Contract\Statistics\{NonConformityEquipmentTypeCount, NonConformityFacilityCount, NonConformitySeverityBucket, NonConformityStatisticsAggregate};
use Inspection\Application\Port\Outbound\{FacilityNamingPort, NonConformityStatisticsGatewayPort};
use Inspection\Application\UseCase\Query\NonConformity\GetNonConformityStatistics\{GetNonConformityStatisticsHandler, GetNonConformityStatisticsQuery};
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionNotFoundException};
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetNonConformityStatisticsHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetNonConformityStatisticsHandler::class)]
final class GetNonConformityStatisticsHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655449901';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655449902';

  private const string FACILITY_A = '550e8400-e29b-41d4-a716-446655449910';

  private const string FACILITY_B = '550e8400-e29b-41d4-a716-446655449911';

  #[Test]
  public function testInvokeZeroFillsEverySeverityAndResolvesFacilityNames(): void
  {
    $aggregate = new NonConformityStatisticsAggregate(
      bySeverity: [
        'critical' => new NonConformitySeverityBucket(open: 2, resolved: 1),
        'low' => new NonConformitySeverityBucket(open: 0, resolved: 3),
      ],
      topFacilities: [
        new NonConformityFacilityCount(facilityId: self::FACILITY_A, open: 4, critical: 2),
        new NonConformityFacilityCount(facilityId: self::FACILITY_B, open: 1, critical: 0),
      ],
      topEquipmentTypes: [
        new NonConformityEquipmentTypeCount(type: 'fire_extinguisher', open: 3),
      ],
      averageResolutionDays: 4.5,
      medianResolutionDays: 3.0,
      slaBreachedOpen: 2,
    );

    $statistics = $this->createMock(NonConformityStatisticsGatewayPort::class);
    $statistics->expects(self::once())
      ->method('aggregate')
      ->with(self::ORGANIZATION_ID, null, null)
      ->willReturn($aggregate);

    $facilityNaming = $this->createMock(FacilityNamingPort::class);
    $facilityNaming->expects(self::once())
      ->method('findNamesByIds')
      ->with([self::FACILITY_A, self::FACILITY_B])
      ->willReturn([self::FACILITY_A => 'Main Warehouse']);

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.inspection.read')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $handler = new GetNonConformityStatisticsHandler(
      statistics: $statistics,
      facilityNaming: $facilityNaming,
      authorization: $authorization,
    );

    $result = $handler->__invoke(new GetNonConformityStatisticsQuery(self::USER_ID, self::ORGANIZATION_ID));

    // All four severity keys, in stable order, zeros included.
    self::assertSame([
      'low' => ['open' => 0, 'resolved' => 3],
      'medium' => ['open' => 0, 'resolved' => 0],
      'high' => ['open' => 0, 'resolved' => 0],
      'critical' => ['open' => 2, 'resolved' => 1],
    ], $result->bySeverity);

    self::assertCount(2, $result->byFacility);
    self::assertSame(self::FACILITY_A, $result->byFacility[0]->facilityId);
    self::assertSame('Main Warehouse', $result->byFacility[0]->facilityName);
    self::assertSame(4, $result->byFacility[0]->open);
    self::assertSame(2, $result->byFacility[0]->critical);
    self::assertSame(self::FACILITY_B, $result->byFacility[1]->facilityId);
    self::assertNull($result->byFacility[1]->facilityName);

    self::assertCount(1, $result->byEquipmentType);
    self::assertSame('fire_extinguisher', $result->byEquipmentType[0]->type);
    self::assertSame(3, $result->byEquipmentType[0]->open);

    self::assertSame(4.5, $result->averageResolutionDays);
    self::assertSame(3.0, $result->medianResolutionDays);
    self::assertSame(2, $result->slaBreachedOpen);
  }

  #[Test]
  public function testInvokePassesTheWindowBoundsToTheGateway(): void
  {
    $from = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $to = new DateTimeImmutable('2026-02-01T00:00:00+00:00');

    $statistics = $this->createMock(NonConformityStatisticsGatewayPort::class);
    $statistics->expects(self::once())
      ->method('aggregate')
      ->with(self::ORGANIZATION_ID, $from, $to)
      ->willReturn($this->emptyAggregate());

    $handler = new GetNonConformityStatisticsHandler(
      statistics: $statistics,
      facilityNaming: $this->namingStub(),
      authorization: $this->grantedAuthorization(),
    );

    $result = $handler->__invoke(new GetNonConformityStatisticsQuery(self::USER_ID, self::ORGANIZATION_ID, $from, $to));

    self::assertNull($result->averageResolutionDays);
    self::assertNull($result->medianResolutionDays);
    self::assertSame(0, $result->slaBreachedOpen);
  }

  #[Test]
  public function testInvokeRejectsAnInvertedWindow(): void
  {
    $statistics = $this->createMock(NonConformityStatisticsGatewayPort::class);
    $statistics->expects(self::never())->method('aggregate');

    $handler = new GetNonConformityStatisticsHandler(
      statistics: $statistics,
      facilityNaming: $this->namingStub(),
      authorization: $this->grantedAuthorization(),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new GetNonConformityStatisticsQuery(
      self::USER_ID,
      self::ORGANIZATION_ID,
      new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ));
  }

  #[Test]
  public function testInvokeThrowsNotFoundForACallerOutsideTheOrganizationScope(): void
  {
    $statistics = $this->createMock(NonConformityStatisticsGatewayPort::class);
    $statistics->expects(self::never())->method('aggregate');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $handler = new GetNonConformityStatisticsHandler(
      statistics: $statistics,
      facilityNaming: $this->namingStub(),
      authorization: $authorization,
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new GetNonConformityStatisticsQuery(self::USER_ID, self::ORGANIZATION_ID));
  }

  #[Test]
  public function testInvokeThrowsAccessDeniedForAMemberWithoutThePermission(): void
  {
    $statistics = $this->createMock(NonConformityStatisticsGatewayPort::class);
    $statistics->expects(self::never())->method('aggregate');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $handler = new GetNonConformityStatisticsHandler(
      statistics: $statistics,
      facilityNaming: $this->namingStub(),
      authorization: $authorization,
    );

    $this->expectException(InspectionAccessDeniedException::class);

    $handler->__invoke(new GetNonConformityStatisticsQuery(self::USER_ID, self::ORGANIZATION_ID));
  }

  private function emptyAggregate(): NonConformityStatisticsAggregate
  {
    return new NonConformityStatisticsAggregate(
      bySeverity: [],
      topFacilities: [],
      topEquipmentTypes: [],
      averageResolutionDays: null,
      medianResolutionDays: null,
      slaBreachedOpen: 0,
    );
  }

  private function namingStub(): FacilityNamingPort
  {
    $naming = $this->createStub(FacilityNamingPort::class);
    $naming->method('findNamesByIds')->willReturn([]);

    return $naming;
  }

  private function grantedAuthorization(): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    return $authorization;
  }
}
