<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Application\UseCase\Reporting;

use DateTimeImmutable;
use Intervention\Application\Contract\Publication\{InterventionEconomicContext, InterventionEconomicContextPage, InterventionEconomicSourceFilter};
use Intervention\Application\Port\Inbound\InterventionPublicationFactsPort;
use Intervention\Application\Port\Outbound\{InterventionEconomicScopePort, InterventionEquipmentSnapshotPort};
use Inventory\Application\Port\Inbound\InventoryInterventionResourcesPort;
use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostItem, MaintenanceCostPlanning, MaintenanceCostTotals, MaintenanceCostView};
use MaintenanceCost\Application\Port\Inbound\{MaintenanceCostReadPort, MaintenanceCurrencyPort};
use MaintenanceCost\Application\Port\Outbound\MaintenanceCostStorePort;
use MaintenanceCost\Application\Service\{MaintenanceCostAccessGuard, MaintenanceEconomicAggregator, MaintenanceEconomicDirectory};
use MaintenanceCost\Application\UseCase\Query\Reporting\ListMaintenanceEconomicDossiers\{ListMaintenanceEconomicDossiersHandler, ListMaintenanceEconomicDossiersQuery};
use MaintenanceCost\Application\UseCase\Query\Reporting\ReadMaintenanceEconomicReport\{ReadMaintenanceEconomicReportHandler, ReadMaintenanceEconomicReportQuery};
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Procurement\Application\Contract\Reporting\{ProcurementEconomicAmount, ProcurementEconomicOverview};
use Procurement\Application\Port\Inbound\ProcurementEconomicOverviewPort;

/**
 * Class MaintenanceEconomicHandlersTest
 *
 * Finance denial and report limits precede any private cost calculation.
 *
 * @category Test
 */
final class MaintenanceEconomicHandlersTest extends TestCase
{
  private const string ORG = '850e8400-e29b-41d4-a716-448040000001';

  /**
   * @return iterable<string,array{OrganizationAccessDecision,string}>
   */
  public static function denials(): iterable
  {
    yield 'missing financial permission' => [OrganizationAccessDecision::MISSING_PERMISSION, 'maintenance_cost_access_denied'];
    yield 'foreign organization' => [OrganizationAccessDecision::OUTSIDE_SCOPE, 'maintenance_cost_not_found'];
  }

  #[DataProvider('denials')]
  public function testDenialNeverReadsOperationalOrFinancialSources(OrganizationAccessDecision $decision, string $reason): void
  {
    $work = $this->createMock(InterventionPublicationFactsPort::class);
    $work->expects(self::never())->method('economicWindow');
    $costs = $this->createMock(MaintenanceCostReadPort::class);
    $costs->expects(self::never())->method('view');
    $procurement = $this->createMock(ProcurementEconomicOverviewPort::class);
    $procurement->expects(self::never())->method('overview');
    $handler = new ReadMaintenanceEconomicReportHandler($this->access($decision), $work, $costs, $this->currencies(), $procurement, new MaintenanceEconomicAggregator());

    try {
      $handler(new ReadMaintenanceEconomicReportQuery('actor', self::ORG, '2026-10-01', '2026-10-31'));
      self::fail('Private reporting must deny this actor.');
    } catch (MaintenanceCostException $error) {
      self::assertSame($reason, $error->reason);
    }
  }

  public function testOversizedDirectoryCountRefusesInsteadOfTruncatingTotals(): void
  {
    $work = $this->createMock(InterventionPublicationFactsPort::class);
    $work->expects(self::once())->method('economicWindow')->willReturn(new InterventionEconomicContextPage([], 501, 1, 501));
    $costs = $this->createMock(MaintenanceCostReadPort::class);
    $costs->expects(self::never())->method('view');
    $procurement = $this->createMock(ProcurementEconomicOverviewPort::class);
    $procurement->expects(self::never())->method('overview');
    $handler = new ReadMaintenanceEconomicReportHandler($this->access(OrganizationAccessDecision::GRANTED), $work, $costs, $this->currencies(), $procurement, new MaintenanceEconomicAggregator());
    $this->expectException(MaintenanceCostException::class);
    $this->expectExceptionMessage('exceeds 500 interventions');
    $handler(new ReadMaintenanceEconomicReportQuery('actor', self::ORG, '2026-10-01', '2026-10-31'));
  }

  public function testExactly500DossiersArePermittedWithoutPartialKnownTotals(): void
  {
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $contexts = [];
    for ($index = 1; $index <= 500; ++$index) {
      $contexts[] = new InterventionEconomicContext('work-' . $index, self::ORG, $index, 'Repair ' . $index, 'corrective_maintenance', 'draft', 0, new DateTimeImmutable('2026-10-07T12:00:00Z'), null, null, null, null, 'live', null, true, null, null, []);
    }
    $work->method('economicWindow')->willReturn(new InterventionEconomicContextPage($contexts, 500, 1, 501));
    $costs = $this->createStub(MaintenanceCostReadPort::class);
    $costs->method('view')->willReturnCallback(static fn (string $org, string $id): MaintenanceCostView => new MaintenanceCostView($id, $org, 'EUR', new MaintenanceCostPlanning(), new MaintenanceCostTotals('10.000000', '10.000000', true, [new MaintenanceCostItem('expense-' . $id, 'expense', null, $id, null, '10.000000', 'EUR', 'Repair', '2026-10-07T12:00:00Z')]), null));
    $procurement = $this->createStub(ProcurementEconomicOverviewPort::class);
    $zero = new ProcurementEconomicAmount('0.000000', '0.000000', true);
    $procurement->method('overview')->willReturn(new ProcurementEconomicOverview(self::ORG, 'EUR', '2026-10-01T00:00:00Z', '2026-11-01T00:00:00Z', 0, 0, 0, $zero, $zero, $zero, $zero));
    $handler = new ReadMaintenanceEconomicReportHandler($this->access(OrganizationAccessDecision::GRANTED), $work, $costs, $this->currencies(), $procurement, new MaintenanceEconomicAggregator());
    $report = $handler(new ReadMaintenanceEconomicReportQuery('actor', self::ORG, '2026-10-01', '2026-10-31'))->report;
    self::assertTrue($report->reconciliation['reconciled']);
    self::assertSame('5000.000000', $report->current->total);
    self::assertSame(500, $report->interventionCount);
  }

  public function testDirectoryScopeAndSearchAreForwardedUnderFinanceReadOnly(): void
  {
    $work = $this->createMock(InterventionPublicationFactsPort::class);
    $work->expects(self::once())->method('economicPage')->with(self::ORG, 2, 10, new InterventionEconomicSourceFilter(search: 'repair'))->willReturn(new InterventionEconomicContextPage([], 15, 2, 10));
    $result = new ListMaintenanceEconomicDossiersHandler($this->access(OrganizationAccessDecision::GRANTED), $work, $this->directory())(new ListMaintenanceEconomicDossiersQuery('actor', self::ORG, 2, 10, 'repair'));
    self::assertSame(15, $result->page->totalItems);
    self::assertSame(2, $result->page->page);
  }

  public function testDirectoryDenialDoesNotResolveAnyNames(): void
  {
    $work = $this->createMock(InterventionPublicationFactsPort::class);
    $work->expects(self::never())->method('economicPage');
    $this->expectException(MaintenanceCostException::class);
    new ListMaintenanceEconomicDossiersHandler($this->access(OrganizationAccessDecision::MISSING_PERMISSION), $work, $this->directory())(new ListMaintenanceEconomicDossiersQuery('actor', self::ORG));
  }

  private function directory(): MaintenanceEconomicDirectory
  {
    $costs = $this->createMock(MaintenanceCostReadPort::class);
    $costs->expects(self::never())->method('view');
    $store = $this->createMock(MaintenanceCostStorePort::class);
    $store->expects(self::never())->method('economicInterventionIds');
    $inventory = $this->createMock(InventoryInterventionResourcesPort::class);
    $inventory->expects(self::never())->method('economicInterventionIds');
    $equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $equipment->expects(self::never())->method('equipmentIdsInFacilities');
    $scopes = $this->createMock(InterventionEconomicScopePort::class);
    $scopes->expects(self::never())->method('facilityIds');

    return new MaintenanceEconomicDirectory($costs, $store, $inventory, $equipment, $scopes);
  }

  private function access(OrganizationAccessDecision $decision): MaintenanceCostAccessGuard
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with('actor', self::ORG, 'organization.maintenance_cost.read')->willReturn($decision);

    return new MaintenanceCostAccessGuard($authorization);
  }

  private function currencies(): MaintenanceCurrencyPort
  {
    $currency = $this->createStub(MaintenanceCurrencyPort::class);
    $currency->method('forOrganization')->willReturn('EUR');

    return $currency;
  }
}
