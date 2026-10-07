<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Park\GetParkAnomaliesSummary;

use Equipment\Application\Port\Inbound\EquipmentParkScopePort;
use Inspection\Application\Contract\Park\ParkAnomaliesCounts;
use Inspection\Application\Port\Outbound\ParkAnomalyGatewayPort;
use Inspection\Application\UseCase\Query\Park\GetParkAnomaliesSummary\{GetParkAnomaliesSummaryHandler, GetParkAnomaliesSummaryQuery};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test GetParkAnomaliesSummaryHandlerTest.
 *
 * @category Tests
 */
#[CoversClass(GetParkAnomaliesSummaryHandler::class)]
final class GetParkAnomaliesSummaryHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '993e8400-e29b-41d4-a716-446655493001';

  #[Test]
  public function testSummaryUsesTheResolvedScopeAndPreservesEverySeverity(): void
  {
    $gateway = $this->createMock(ParkAnomalyGatewayPort::class);
    $gateway->expects(self::once())->method('findCandidateEquipmentIds')->with(self::ORGANIZATION_ID)->willReturn(['fire-equipment', 'camera-equipment']);
    $scope = $this->createMock(EquipmentParkScopePort::class);
    $scope->expects(self::once())->method('filterIds')->with(self::ORGANIZATION_ID, ['fire-equipment', 'camera-equipment'], 'fire', 'customer-id', 'facility-id', false)->willReturn(['fire-equipment']);
    $gateway->expects(self::once())->method('summary')->with(self::ORGANIZATION_ID, ['fire-equipment'])->willReturn(new ParkAnomaliesCounts(10, ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4]));
    $result = new GetParkAnomaliesSummaryHandler($gateway, $scope)(new GetParkAnomaliesSummaryQuery(self::ORGANIZATION_ID, 'fire', 'customer-id', 'facility-id', includeDescendants: false));
    self::assertSame(10, $result->openAnomalies);
    self::assertSame(['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4], $result->bySeverity);
  }

  #[Test]
  public function testEmptyCandidatesStillResolveScopeAndReturnAllZeroBuckets(): void
  {
    $gateway = $this->createMock(ParkAnomalyGatewayPort::class);
    $gateway->method('findCandidateEquipmentIds')->willReturn([]);
    $scope = $this->createMock(EquipmentParkScopePort::class);
    $scope->expects(self::once())->method('filterIds')->with(self::ORGANIZATION_ID, [], null, null, null)->willReturn([]);
    $gateway->expects(self::once())->method('summary')->with(self::ORGANIZATION_ID, [])->willReturn(new ParkAnomaliesCounts(0));
    $result = new GetParkAnomaliesSummaryHandler($gateway, $scope)(new GetParkAnomaliesSummaryQuery(self::ORGANIZATION_ID));
    self::assertSame(0, $result->openAnomalies);
    self::assertSame(['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0], $result->bySeverity);
  }

  #[Test]
  public function testInvalidFamilyRemainsInvalidWithoutCandidates(): void
  {
    $gateway = $this->createMock(ParkAnomalyGatewayPort::class);
    $gateway->method('findCandidateEquipmentIds')->willReturn([]);
    $gateway->expects(self::never())->method('summary');
    $scope = $this->createMock(EquipmentParkScopePort::class);
    $scope->expects(self::once())->method('filterIds')->with(self::ORGANIZATION_ID, [], 'invalid', null, null)->willThrowException(new RuntimeException('Invalid family.'));
    $this->expectException(RuntimeException::class);
    new GetParkAnomaliesSummaryHandler($gateway, $scope)(new GetParkAnomaliesSummaryQuery(self::ORGANIZATION_ID, 'invalid'));
  }
}
