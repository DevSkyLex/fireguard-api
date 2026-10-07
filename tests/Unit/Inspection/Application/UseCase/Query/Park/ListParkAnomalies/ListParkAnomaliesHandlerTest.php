<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Park\ListParkAnomalies;

use DateTimeImmutable;
use Equipment\Application\Port\Inbound\EquipmentParkScopePort;
use Inspection\Application\Contract\Park\ParkAnomalyEntry;
use Inspection\Application\Port\Outbound\{EquipmentNamingPort, ParkAnomalyGatewayPort};
use Inspection\Application\UseCase\Query\Park\ListParkAnomalies\{ListParkAnomaliesHandler, ListParkAnomaliesQuery};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test ListParkAnomaliesHandlerTest.
 *
 * @category Tests
 */
#[CoversClass(ListParkAnomaliesHandler::class)]
final class ListParkAnomaliesHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '993e8400-e29b-41d4-a716-446655493001';

  private const string EQUIPMENT_ID = '993e8400-e29b-41d4-a716-446655493010';

  #[Test]
  public function testScopeIsResolvedBeforePaginationAndPageNamingIsBatched(): void
  {
    $pagination = new Pagination(20, 2);
    $sorting = new Sorting('createdAt', SortDirection::DESC);
    $candidateIds = [self::EQUIPMENT_ID, '993e8400-e29b-41d4-a716-446655493011'];
    $gateway = $this->createMock(ParkAnomalyGatewayPort::class);
    $gateway->expects(self::once())->method('findCandidateEquipmentIds')->with(self::ORGANIZATION_ID)->willReturn($candidateIds);
    $scope = $this->createMock(EquipmentParkScopePort::class);
    $scope->expects(self::once())->method('filterIds')->with(self::ORGANIZATION_ID, $candidateIds, 'fire', 'customer-id', 'facility-id', false)->willReturn([self::EQUIPMENT_ID]);
    $gateway->expects(self::once())->method('list')->with(self::ORGANIZATION_ID, [self::EQUIPMENT_ID], $pagination, $sorting)->willReturn([
      $this->entry('993e8400-e29b-41d4-a716-446655493030'),
      $this->entry('993e8400-e29b-41d4-a716-446655493031'),
    ]);
    $gateway->expects(self::once())->method('count')->with(self::ORGANIZATION_ID, [self::EQUIPMENT_ID])->willReturn(7);
    $naming = $this->createMock(EquipmentNamingPort::class);
    $naming->expects(self::once())->method('findSerialNumbersByIds')->with([self::EQUIPMENT_ID])->willReturn([self::EQUIPMENT_ID => 'SN-FIRE-01']);
    $result = new ListParkAnomaliesHandler($gateway, $scope, $naming)(new ListParkAnomaliesQuery(self::ORGANIZATION_ID, 'fire', 'customer-id', 'facility-id', $pagination, $sorting, includeDescendants: false));

    self::assertCount(2, $result->items);
    self::assertSame(7, $result->total);
    self::assertSame(2, $result->limit);
    self::assertSame(20, $result->offset);
    self::assertSame(self::EQUIPMENT_ID, $result->items[0]->equipmentId);
    self::assertSame('SN-FIRE-01', $result->items[0]->equipmentSerialNumber);
    self::assertSame('Open defect', $result->items[0]->description);
    self::assertSame('high', $result->items[0]->severity);
    self::assertSame('open', $result->items[0]->status);
    self::assertSame('2026-10-06T10:00:00+00:00', $result->items[0]->dueAt);
    self::assertSame('Keep isolated', $result->items[0]->notes);
  }

  #[Test]
  public function testEmptyCandidatesStillValidateScopesAndAvoidNaming(): void
  {
    $gateway = $this->createMock(ParkAnomalyGatewayPort::class);
    $gateway->method('findCandidateEquipmentIds')->willReturn([]);
    $gateway->expects(self::once())->method('list')->with(self::ORGANIZATION_ID, [], new Pagination(), new Sorting('createdAt', SortDirection::DESC))->willReturn([]);
    $gateway->expects(self::once())->method('count')->with(self::ORGANIZATION_ID, [])->willReturn(0);
    $scope = $this->createMock(EquipmentParkScopePort::class);
    $scope->expects(self::once())->method('filterIds')->with(self::ORGANIZATION_ID, [], 'fire', null, null)->willReturn([]);
    $naming = $this->createMock(EquipmentNamingPort::class);
    $naming->expects(self::never())->method('findSerialNumbersByIds');
    $result = new ListParkAnomaliesHandler($gateway, $scope, $naming)(new ListParkAnomaliesQuery(self::ORGANIZATION_ID, 'fire'));

    self::assertSame([], $result->items);
    self::assertSame(0, $result->total);
  }

  #[Test]
  public function testInvalidScopeOnEmptyCandidatesDoesNotQueryAPage(): void
  {
    $gateway = $this->createMock(ParkAnomalyGatewayPort::class);
    $gateway->method('findCandidateEquipmentIds')->willReturn([]);
    $gateway->expects(self::never())->method('list');
    $gateway->expects(self::never())->method('count');
    $scope = $this->createMock(EquipmentParkScopePort::class);
    $scope->expects(self::once())->method('filterIds')->with(self::ORGANIZATION_ID, [], null, 'foreign-customer', null)->willThrowException(new RuntimeException('Customer not found.'));
    $this->expectException(RuntimeException::class);
    new ListParkAnomaliesHandler($gateway, $scope, $this->createStub(EquipmentNamingPort::class))(new ListParkAnomaliesQuery(self::ORGANIZATION_ID, customerId: 'foreign-customer'));
  }

  #[Test]
  public function testInvalidPaginationNeverReadsCandidates(): void
  {
    $gateway = $this->createMock(ParkAnomalyGatewayPort::class);
    $gateway->expects(self::never())->method('findCandidateEquipmentIds');
    $this->expectException(InvalidValueException::class);
    new ListParkAnomaliesHandler($gateway, $this->createStub(EquipmentParkScopePort::class), $this->createStub(EquipmentNamingPort::class))(new ListParkAnomaliesQuery(self::ORGANIZATION_ID, pagination: new Pagination(-1, 20)));
  }

  #[Test]
  public function testUnknownSerialNumberRemainsNull(): void
  {
    $gateway = $this->createStub(ParkAnomalyGatewayPort::class);
    $gateway->method('findCandidateEquipmentIds')->willReturn([self::EQUIPMENT_ID]);
    $gateway->method('list')->willReturn([$this->entry('993e8400-e29b-41d4-a716-446655493030')]);
    $gateway->method('count')->willReturn(1);
    $scope = $this->createStub(EquipmentParkScopePort::class);
    $scope->method('filterIds')->willReturn([self::EQUIPMENT_ID]);
    $naming = $this->createStub(EquipmentNamingPort::class);
    $naming->method('findSerialNumbersByIds')->willReturn([]);
    $result = new ListParkAnomaliesHandler($gateway, $scope, $naming)(new ListParkAnomaliesQuery(self::ORGANIZATION_ID));
    self::assertNull($result->items[0]->equipmentSerialNumber);
  }

  /**
   * Method entry.
   *
   * @param string $id finding identifier
   *
   * @return ParkAnomalyEntry published unresolved finding
   */
  private function entry(string $id): ParkAnomalyEntry
  {
    $at = new DateTimeImmutable('2026-10-06T10:00:00+00:00');

    return new ParkAnomalyEntry($id, '993e8400-e29b-41d4-a716-446655493020', self::EQUIPMENT_ID, 'Open defect', 'high', 'open', $at, null, 'Keep isolated', $at, $at);
  }
}
