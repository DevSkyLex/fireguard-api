<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Contract\Publication\{InterventionEconomicContext, InterventionEquipmentSnapshot, InterventionPublishedWorkFact};
use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostItem, MaintenanceCostPlanning, MaintenanceCostSnapshot, MaintenanceCostTotals, MaintenanceCostView};
use MaintenanceCost\Application\Contract\Reporting\{MaintenanceEconomicReport, MaintenanceEconomicRow};
use MaintenanceCost\Application\Service\MaintenanceEconomicAggregator;
use MaintenanceCost\Application\UseCase\Query\Reporting\ReadMaintenanceEconomicReport\ReadMaintenanceEconomicReportQuery;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Procurement\Application\Contract\Reporting\{ProcurementEconomicAmount, ProcurementEconomicOverview};

use function bcadd;
use function is_numeric;

/**
 * Class MaintenanceEconomicAggregatorTest
 *
 * Proves exact allocation and reconciliation without multiplying shared dossier costs across assets.
 *
 * @category Test
 */
final class MaintenanceEconomicAggregatorTest extends TestCase
{
  // #region Methods
  /**
   * Method testEachTaskOrDirectMaterialFactIsAllocatedOnceAndGlobalExpenseRemainsUnallocated
   *
   * @access public
   *
   * @return void
   */
  public function testEachTaskOrDirectMaterialFactIsAllocatedOnceAndGlobalExpenseRemainsUnallocated(): void
  {
    $context = $this->context();
    $report = $this->report($context, [
      $this->item('labor', '12.100000', workItemId: 'task-a', kind: 'time'),
      $this->item('part', '7.200000', equipmentId: 'equipment-b', kind: 'material'),
      $this->item('travel', '5.000000'),
    ]);

    self::assertSame('24.300000', $report->current->total);
    self::assertSame(3, $report->current->contributionCount);
    self::assertSame('12.100000', $this->row($report, 'equipment-a')->current->total);
    self::assertSame('7.200000', $this->row($report, 'equipment-b')->current->total);
    self::assertSame('5.000000', $report->unallocated->current->total);
    self::assertSame('unallocated', $report->unallocated->identityState);
    self::assertFalse($report->unallocated->allocationComplete);
    self::assertSame(['intervention-a'], $this->row($report, 'equipment-a')->interventionIds);
    self::assertTrue($report->reconciliation['reconciled']);
  }

  /**
   * Method testSiteGroupingCombinesEquipmentFactsAndKeepsTheGlobalExpenseSeparate
   *
   * @access public
   *
   * @return void
   */
  public function testSiteGroupingCombinesEquipmentFactsAndKeepsTheGlobalExpenseSeparate(): void
  {
    $report = $this->report($this->context(), [
      $this->item('first', '10.000000', workItemId: 'task-a'),
      $this->item('second', '20.000000', workItemId: 'task-b'),
      $this->item('shared', '3.000000'),
    ], query: $this->query(groupBy: 'site'));

    self::assertSame(1, $report->totalItems);
    self::assertSame('site-a', $report->rows[0]->id);
    self::assertSame('30.000000', $report->rows[0]->current->total);
    self::assertSame('3.000000', $report->unallocated->current->total);
    self::assertSame('33.000000', $report->current->total);
  }

  /**
   * Method testCustomerGroupingCombinesDifferentSitesWithoutDuplicatingAnIntervention
   *
   * @access public
   *
   * @return void
   */
  public function testCustomerGroupingCombinesDifferentSitesWithoutDuplicatingAnIntervention(): void
  {
    $customer = ['id' => 'customer-a', 'name' => 'Hospital'];
    $context = $this->context(tasks: [
      $this->task('task-a', 'equipment-a', ['id' => 'site-a', 'name' => 'East'], $customer),
      $this->task('task-b', 'equipment-b', ['id' => 'site-b', 'name' => 'West'], $customer),
    ]);
    $report = $this->report($context, [
      $this->item('east', '10.000000', workItemId: 'task-a'),
      $this->item('west', '20.000000', workItemId: 'task-b'),
    ], query: $this->query(groupBy: 'customer'));

    self::assertSame(1, $report->totalItems);
    self::assertSame('customer-a', $report->rows[0]->id);
    self::assertSame('Hospital', $report->rows[0]->name);
    self::assertSame('30.000000', $report->rows[0]->current->total);
    self::assertSame(['intervention-a'], $report->rows[0]->interventionIds);
  }

  /**
   * Method testEquipmentFilterRetainsUnallocatedFactsAndReconcilesExcludedTargets
   *
   * @access public
   *
   * @return void
   */
  public function testEquipmentFilterRetainsUnallocatedFactsAndReconcilesExcludedTargets(): void
  {
    $report = $this->report($this->context(), [
      $this->item('first', '10.000000', workItemId: 'task-a'),
      $this->item('second', '20.000000', workItemId: 'task-b'),
      $this->item('shared', '3.000000'),
    ], query: $this->query(equipmentId: 'equipment-a'));

    self::assertSame(1, $report->totalItems);
    self::assertSame('13.000000', $report->current->total);
    self::assertSame('3.000000', $report->unallocated->current->total);
    self::assertSame('33.000000', $report->reconciliation['sourceCurrent']->total);
    self::assertSame('20.000000', $report->reconciliation['excludedCurrent']->total);
    self::assertTrue($report->reconciliation['reconciled']);
  }

  /**
   * Method testTargetFiltersAreConjunctiveAndPreserveTheUnallocatedContribution
   *
   * @access public
   *
   * @param ?string $siteId selected site
   * @param ?string $customerId selected customer
   * @param string $expected selected exact cost
   * @param string $unallocated global cost within matching dossiers
   *
   * @return void
   */
  #[DataProvider('targetFilterCases')]
  public function testTargetFiltersAreConjunctiveAndPreserveTheUnallocatedContribution(?string $siteId, ?string $customerId, string $expected, string $unallocated): void
  {
    $context = $this->context(tasks: [
      $this->task('task-a', 'equipment-a', ['id' => 'site-a', 'name' => 'East'], ['id' => 'customer-a', 'name' => 'Alpha']),
      $this->task('task-b', 'equipment-b', ['id' => 'site-b', 'name' => 'West'], ['id' => 'customer-b', 'name' => 'Beta']),
    ]);
    $report = $this->report($context, [
      $this->item('first', '10.000000', workItemId: 'task-a'),
      $this->item('second', '20.000000', workItemId: 'task-b'),
      $this->item('shared', '3.000000'),
    ], query: $this->query(siteId: $siteId, customerId: $customerId));

    self::assertSame($expected, $report->current->total);
    self::assertSame($unallocated, $report->unallocated->current->total);
    self::assertTrue($report->reconciliation['reconciled']);
  }

  /**
   * Method targetFilterCases
   *
   * @access public
   *
   * @return iterable<string,array{?string,?string,string,string}> independently selected and incompatible filters
   */
  public static function targetFilterCases(): iterable
  {
    yield 'site' => ['site-a', null, '13.000000', '3.000000'];
    yield 'customer' => [null, 'customer-b', '23.000000', '3.000000'];
    yield 'incompatible site and customer excludes this dossier' => ['site-a', 'customer-b', '0.000000', '0.000000'];
  }

  /**
   * Method testMissingHistoricSnapshotProducesUnknownFrozenCostWithoutGuessingLiveIdentity
   *
   * @access public
   *
   * @return void
   */
  public function testMissingHistoricSnapshotProducesUnknownFrozenCostWithoutGuessingLiveIdentity(): void
  {
    $context = $this->context(status: 'published', snapshotState: 'snapshot_missing', tasks: []);
    $report = $this->report($context, [$this->item('legacy-shared', '25.000000')], query: $this->query(groupBy: 'site'));

    self::assertSame([], $report->rows);
    self::assertSame(1, $report->missingSnapshotCount);
    self::assertSame(1, $report->publishedInterventionCount);
    self::assertSame(0, $report->liveInterventionCount);
    self::assertNull($report->frozen->total);
    self::assertFalse($report->frozen->complete);
    self::assertSame(1, $report->frozen->unknownCount);
    self::assertSame('0.000000', $report->frozen->knownTotal);
    self::assertSame('25.000000', $report->unallocated->current->total);
    self::assertNull($report->unallocated->frozen->total);
  }

  /**
   * Method testUnknownContributionKeepsKnownSubtotalAndSuppressesVariance
   *
   * @access public
   *
   * @return void
   */
  public function testUnknownContributionKeepsKnownSubtotalAndSuppressesVariance(): void
  {
    $report = $this->report($this->context(), [
      $this->item('known', '12.500001', workItemId: 'task-a'),
      $this->item('unpriced', null, workItemId: 'task-a', kind: 'material'),
      $this->item('correction', '-2.000000', workItemId: 'task-a'),
    ], new MaintenanceCostPlanning('8.000000'));

    self::assertNull($report->current->total);
    self::assertSame('10.500001', $report->current->knownTotal);
    self::assertSame(1, $report->current->unknownCount);
    self::assertSame(3, $report->current->contributionCount);
    self::assertNull($report->variance);
    self::assertFalse($this->row($report, 'equipment-a')->current->complete);
  }

  /**
   * Method testPlannedResourcesAreComparedToActualCostWithoutAddingTheGlobalBudgetAgain
   *
   * @access public
   *
   * @return void
   */
  public function testPlannedResourcesAreComparedToActualCostWithoutAddingTheGlobalBudgetAgain(): void
  {
    $planning = new MaintenanceCostPlanning('200.000000', resources: [
      $this->resource('task-a', '60.000000'),
      $this->resource('task-b', '30.000000'),
    ]);
    $report = $this->report($this->context(), [
      $this->item('first', '70.000000', workItemId: 'task-a'),
      $this->item('second', '30.000000', workItemId: 'task-b'),
    ], $planning);

    self::assertSame('90.000000', $report->planned->total);
    self::assertSame('200.000000', $report->budget->total);
    self::assertSame('200.000000', $report->unallocated->budget->total);
    self::assertSame('0.000000', $report->unallocated->planned->total);
    self::assertSame('60.000000', $this->row($report, 'equipment-a')->planned->total);
    self::assertSame('10.000000', $report->variance);
    self::assertSame('10.000000', $this->row($report, 'equipment-a')->variance);
  }

  /**
   * Method testFilteringPlanningResourcesReconcilesTheExcludedEstimate
   *
   * @access public
   *
   * @return void
   */
  public function testFilteringPlanningResourcesReconcilesTheExcludedEstimate(): void
  {
    $report = $this->report($this->context(), [], new MaintenanceCostPlanning('100.000000', resources: [
      $this->resource('task-a', '20.000000'),
      $this->resource('task-b', '30.000000'),
      $this->resource(null, '4.000000'),
    ]), query: $this->query(equipmentId: 'equipment-a'));

    self::assertSame('24.000000', $report->planned->total);
    self::assertSame('54.000000', $report->reconciliation['sourcePlanned']->total);
    self::assertSame('30.000000', $report->reconciliation['excludedPlanned']->total);
    self::assertSame('4.000000', $report->unallocated->planned->total);
    self::assertSame('100.000000', $report->budget->total);
    self::assertTrue($report->reconciliation['reconciled']);
  }

  /**
   * Method testVarianceRequiresBothCompleteTotals
   *
   * @access public
   *
   * @param ?string $actual actual known contribution or unknown
   * @param ?string $estimate planned known resource or unknown
   * @param ?string $expected exact variance when both sides are known
   *
   * @return void
   */
  #[DataProvider('varianceCases')]
  public function testVarianceRequiresBothCompleteTotals(?string $actual, ?string $estimate, ?string $expected): void
  {
    $report = $this->report($this->context(), [$this->item('actual', $actual, workItemId: 'task-a')], new MaintenanceCostPlanning('999.000000', resources: [$this->resource('task-a', $estimate)]));

    self::assertSame($expected, $report->variance);
    self::assertSame($expected, $this->row($report, 'equipment-a')->variance);
    self::assertSame(null !== $estimate, $report->planned->complete);
    self::assertSame(null !== $estimate ? 0 : 1, $report->planned->unknownCount);
  }

  /**
   * Method varianceCases
   *
   * @access public
   *
   * @return iterable<string,array{?string,?string,?string}> missing valuation combinations
   */
  public static function varianceCases(): iterable
  {
    yield 'complete' => ['12.000001', '10.000000', '2.000001'];
    yield 'unknown current' => [null, '10.000000', null];
    yield 'unknown planning' => ['12.000001', null, null];
    yield 'both unknown' => [null, null, null];
  }

  /**
   * Method testMissingPlanningRemainsUnknownInsteadOfAZeroEstimate
   *
   * @access public
   *
   * @return void
   */
  public function testMissingPlanningRemainsUnknownInsteadOfAZeroEstimate(): void
  {
    $report = $this->report($this->context(), [$this->item('actual', '15.000000', workItemId: 'task-a')]);

    self::assertNull($report->planned->total);
    self::assertSame('0.000000', $report->planned->knownTotal);
    self::assertSame(1, $report->planned->unknownCount);
    self::assertNull($report->variance);
    self::assertNull($this->row($report, 'equipment-a')->variance);
  }

  /**
   * Method testLateCurrentFactsDoNotChangeFrozenAmountsOrCapturedPlanning
   *
   * @access public
   *
   * @return void
   */
  public function testLateCurrentFactsDoNotChangeFrozenAmountsOrCapturedPlanning(): void
  {
    $context = $this->context(status: 'published', snapshotState: 'available');
    $original = $this->item('original', '20.000000', workItemId: 'task-a');
    $snapshot = $this->snapshot([$original], new MaintenanceCostPlanning('12.000000', resources: [$this->resource('task-a', '12.000000')]));
    $report = $this->report($context, [$original, $this->item('late', '5.000000', workItemId: 'task-a')], new MaintenanceCostPlanning('900.000000'), $snapshot);

    self::assertSame('25.000000', $report->current->total);
    self::assertSame('20.000000', $report->frozen->total);
    self::assertSame('12.000000', $report->planned->total);
    self::assertSame('12.000000', $report->budget->total);
    self::assertSame('13.000000', $report->variance);
    self::assertSame('20.000000', $this->row($report, 'equipment-a')->frozen->total);
    self::assertSame(0, $report->missingSnapshotCount);
  }

  /**
   * Method testExplicitCapturedAllocationsRetainTheirOriginalLabelsInsteadOfCurrentTaskLabels
   *
   * @access public
   *
   * @return void
   */
  public function testExplicitCapturedAllocationsRetainTheirOriginalLabelsInsteadOfCurrentTaskLabels(): void
  {
    $allocation = [
      'identityState' => 'captured',
      'equipment' => ['id' => 'equipment-a', 'name' => 'Original extinguisher', 'assetReference' => 'EXT-OLD'],
      'site' => ['id' => 'site-a', 'name' => 'Original site'],
      'customer' => ['id' => 'customer-a', 'name' => 'Original customer'],
    ];
    $item = $this->item('original', '20.000000', workItemId: 'task-a', allocation: $allocation);
    $report = $this->report($this->context(status: 'published', snapshotState: 'available'), [$item], frozen: $this->snapshot([$item]));

    self::assertSame('Original extinguisher', $this->row($report, 'equipment-a')->name);
    self::assertSame('captured', $this->row($report, 'equipment-a')->identityState);
    self::assertTrue($this->row($report, 'equipment-a')->allocationComplete);
    self::assertSame('20.000000', $this->row($report, 'equipment-a')->frozen->total);
  }

  /**
   * Method testAggregateMayExceedTheEighteenDigitLimitOfAnIndividualFactWithoutLosingPrecision
   *
   * @access public
   *
   * @return void
   */
  public function testAggregateMayExceedTheEighteenDigitLimitOfAnIndividualFactWithoutLosingPrecision(): void
  {
    $report = $this->report($this->context(), [
      $this->item('first', '999999999999999999.999999', workItemId: 'task-a'),
      $this->item('second', '999999999999999999.999999', workItemId: 'task-a'),
    ]);

    self::assertSame('1999999999999999999.999998', $report->current->total);
    self::assertSame('1999999999999999999.999998', $this->row($report, 'equipment-a')->current->total);
    self::assertSame('1999999999999999999.999998', $report->reconciliation['sourceCurrent']->total);
  }

  /**
   * Method testPaginationDoesNotChangeFullScopeTotalsOrCounts
   *
   * @access public
   *
   * @return void
   */
  public function testPaginationDoesNotChangeFullScopeTotalsOrCounts(): void
  {
    $context = $this->context(tasks: [
      $this->task('task-a', 'equipment-a'),
      $this->task('task-b', 'equipment-b'),
      $this->task('task-c', 'equipment-c'),
    ]);
    $report = $this->report($context, [
      $this->item('first', '10.000000', workItemId: 'task-a'),
      $this->item('second', '20.000000', workItemId: 'task-b'),
      $this->item('third', '30.000000', workItemId: 'task-c'),
    ], query: $this->query(page: 2, itemsPerPage: 1));

    self::assertSame(3, $report->totalItems);
    self::assertCount(1, $report->rows);
    self::assertSame('equipment-b', $report->rows[0]->id);
    self::assertSame('20.000000', $report->rows[0]->current->total);
    self::assertSame('60.000000', $report->current->total);
    self::assertSame('60.000000', $report->reconciliation['sourceCurrent']->total);
    self::assertSame(1, $report->interventionCount);
    self::assertSame([['id' => 'intervention-a', 'number' => 1, 'name' => 'Annual campaign', 'status' => 'preparing', 'snapshotState' => 'live']], $report->dossiers);
  }

  /**
   * Method testForeignOrganizationSourcesAreRefused
   *
   * @access public
   *
   * @param bool $foreignContext whether the operational source belongs to another organization
   *
   * @return void
   */
  #[DataProvider('foreignOrganizationCases')]
  public function testForeignOrganizationSourcesAreRefused(bool $foreignContext): void
  {
    $context = $this->context(organizationId: $foreignContext ? 'other-organization' : 'organization-a');
    $view = $this->view($context, [], organizationId: $foreignContext ? 'organization-a' : 'other-organization');

    $this->assertConflict($context, $view);
  }

  /**
   * Method foreignOrganizationCases
   *
   * @access public
   *
   * @return iterable<string,array{bool}> every cross-module source is organization-scoped
   */
  public static function foreignOrganizationCases(): iterable
  {
    yield 'operational context' => [true];
    yield 'financial view' => [false];
  }

  /**
   * Method testMixedCurrencySourcesAreRefused
   *
   * @access public
   *
   * @param string $source source carrying another currency
   *
   * @return void
   */
  #[DataProvider('mixedCurrencyCases')]
  public function testMixedCurrencySourcesAreRefused(string $source): void
  {
    $context = $this->context();
    $view = $this->view($context, [$this->item('actual', '1.000000', currency: 'current' === $source ? 'USD' : 'EUR')], frozen: $this->snapshot([$this->item('historic', '1.000000', currency: 'frozen' === $source ? 'USD' : 'EUR')]), currency: 'view' === $source ? 'USD' : 'EUR');

    $this->assertConflict($context, $view);
  }

  /**
   * Method mixedCurrencyCases
   *
   * @access public
   *
   * @return iterable<string,array{string}> source currency violations
   */
  public static function mixedCurrencyCases(): iterable
  {
    yield 'current contribution' => ['current'];
    yield 'frozen contribution' => ['frozen'];
    yield 'financial view' => ['view'];
  }

  /**
   * Method testMissingFinancialViewFailsClosed
   *
   * @access public
   *
   * @return void
   */
  public function testMissingFinancialViewFailsClosed(): void
  {
    $this->expectException(MaintenanceCostException::class);
    $this->expectExceptionMessage('Maintenance cost resource not found.');
    new MaintenanceEconomicAggregator()->report($this->query(), 'EUR', [$this->context()], [], $this->procurement());
  }

  /**
   * Method report
   *
   * @access private
   *
   * @param InterventionEconomicContext $context scoped work dossier
   * @param list<MaintenanceCostItem> $items received current facts
   * @param ?MaintenanceCostPlanning $planning current estimate
   * @param ?MaintenanceCostSnapshot $frozen original publication facts
   * @param ?ReadMaintenanceEconomicReportQuery $query selected destination scope
   *
   * @return MaintenanceEconomicReport actual report under test
   */
  private function report(InterventionEconomicContext $context, array $items, ?MaintenanceCostPlanning $planning = null, ?MaintenanceCostSnapshot $frozen = null, ?ReadMaintenanceEconomicReportQuery $query = null): MaintenanceEconomicReport
  {
    return new MaintenanceEconomicAggregator()->report($query ?? $this->query(), 'EUR', [$context], [$context->id => $this->view($context, $items, $planning, $frozen)], $this->procurement());
  }

  /**
   * Method context
   *
   * @access private
   *
   * @param string $status current work lifecycle
   * @param 'available'|'snapshot_missing'|'live' $snapshotState retained identity provenance
   * @param ?list<InterventionPublishedWorkFact> $tasks explicit targets; absent uses two assets
   * @param string $organizationId source owner
   *
   * @return InterventionEconomicContext operational source fixture
   */
  private function context(string $status = 'preparing', string $snapshotState = 'live', ?array $tasks = null, string $organizationId = 'organization-a'): InterventionEconomicContext
  {
    return new InterventionEconomicContext('intervention-a', $organizationId, 1, 'Annual campaign', 'control', $status, 7, new DateTimeImmutable('2026-01-01T00:00:00Z'), null, null, 'published' === $status ? new DateTimeImmutable('2026-02-01T00:00:00Z') : null, 'published' === $status ? 'publication-a' : null, $snapshotState, 'available' === $snapshotState ? 1 : null, 'snapshot_missing' !== $snapshotState, ['id' => 'site-a', 'name' => 'Current root site'], ['id' => 'customer-a', 'name' => 'Current customer'], $tasks ?? [$this->task('task-a', 'equipment-a'), $this->task('task-b', 'equipment-b')]);
  }

  /**
   * Method task
   *
   * @access private
   *
   * @param string $id task identity
   * @param string $equipmentId owned asset
   * @param ?array{id:string,name:string} $site allocation site
   * @param ?array{id:string,name:string} $customer allocation client
   *
   * @return InterventionPublishedWorkFact minimal captured work fixture
   */
  private function task(string $id, string $equipmentId, ?array $site = null, ?array $customer = null): InterventionPublishedWorkFact
  {
    $site ??= ['id' => 'site-a', 'name' => 'Current root site'];
    $customer ??= ['id' => 'customer-a', 'name' => 'Current customer'];
    $identity = new InterventionEquipmentSnapshot($equipmentId, $equipmentId, 'ASSET-' . $equipmentId, 'extinguisher', null, null, null, 'facility-a', $site, $customer);

    return new InterventionPublishedWorkFact($id, 'control', 'completed', '/api/equipments/' . $equipmentId, null, $equipmentId, $site, $customer, $identity, null, true, 0, 0, null, null);
  }

  /**
   * Method item
   *
   * @access private
   *
   * @param string $id stable source identity
   * @param ?string $amount exact valuation or unknown
   * @param ?string $workItemId explicitly linked task
   * @param ?string $equipmentId direct physical target
   * @param string $kind source contribution category
   * @param string $currency contribution currency
   * @param ?array{identityState:string,equipment:?array{id:string,name:?string,assetReference:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}} $allocation captured private allocation overriding live labels
   *
   * @return MaintenanceCostItem source fixture
   */
  private function item(string $id, ?string $amount, ?string $workItemId = null, ?string $equipmentId = null, string $kind = 'expense', string $currency = 'EUR', ?array $allocation = null): MaintenanceCostItem
  {
    return new MaintenanceCostItem($id, $kind, $workItemId, $id, 1, $amount, $currency, 'Contribution ' . $id, '2026-02-01T00:00:00Z', equipmentId: $equipmentId, allocation: $allocation);
  }

  /**
   * Method resource
   *
   * @access private
   *
   * @param ?string $taskId explicit allocation, absent for dossier-wide estimate
   * @param ?string $amount exact planned valuation or unknown
   *
   * @return array{workItemId:?string,kind:string,description:string,quantity:?string,unitCost:?string,estimatedMinutes:?int,amount:?string} public prepared resource
   */
  private function resource(?string $taskId, ?string $amount): array
  {
    return ['workItemId' => $taskId, 'kind' => 'external', 'description' => 'Planned service', 'quantity' => null, 'unitCost' => null, 'estimatedMinutes' => null, 'amount' => $amount];
  }

  /**
   * Method view
   *
   * @access private
   *
   * @param InterventionEconomicContext $context originating work
   * @param list<MaintenanceCostItem> $items current facts
   * @param ?MaintenanceCostPlanning $planning prepared resources
   * @param ?MaintenanceCostSnapshot $frozen published original
   * @param ?string $organizationId override for isolation refusal
   * @param string $currency configured finance currency
   *
   * @return MaintenanceCostView private source fixture
   */
  private function view(InterventionEconomicContext $context, array $items, ?MaintenanceCostPlanning $planning = null, ?MaintenanceCostSnapshot $frozen = null, ?string $organizationId = null, string $currency = 'EUR'): MaintenanceCostView
  {
    return new MaintenanceCostView($context->id, $organizationId ?? $context->organizationId, $currency, $planning ?? new MaintenanceCostPlanning(), $this->totals($items), $frozen);
  }

  /**
   * Method snapshot
   *
   * @access private
   *
   * @param list<MaintenanceCostItem> $items original received facts
   * @param ?MaintenanceCostPlanning $planning original prepared resources
   *
   * @return MaintenanceCostSnapshot immutable source fixture
   */
  private function snapshot(array $items, ?MaintenanceCostPlanning $planning = null): MaintenanceCostSnapshot
  {
    return new MaintenanceCostSnapshot(1, '2026-02-01T00:00:00Z', 'publication-a', 7, 'EUR', $this->totals($items), $planning);
  }

  /**
   * Method totals
   *
   * @access private
   *
   * @param list<MaintenanceCostItem> $items literal exact source values
   *
   * @return MaintenanceCostTotals consistent source totals independent of allocation
   */
  private function totals(array $items): MaintenanceCostTotals
  {
    $sum = '0.000000';
    $complete = true;
    foreach ($items as $item) {
      if (null === $item->amount) {
        $complete = false;
      } else {
        if (!is_numeric($item->amount)) {
          self::fail('Cost fixture must retain a literal exact decimal.');
        }
        $sum = bcadd($sum, $item->amount, 6);
      }
    }

    return new MaintenanceCostTotals($complete ? $sum : null, $sum, $complete, $items);
  }

  /**
   * Method query
   *
   * @access private
   *
   * @param string $groupBy selected economic destination
   * @param ?string $siteId retained site filter
   * @param ?string $customerId retained client filter
   * @param ?string $equipmentId retained equipment filter
   * @param int $page requested page
   * @param int $itemsPerPage allocated rows per page
   *
   * @return ReadMaintenanceEconomicReportQuery report request fixture
   */
  private function query(string $groupBy = 'equipment', ?string $siteId = null, ?string $customerId = null, ?string $equipmentId = null, int $page = 1, int $itemsPerPage = 30): ReadMaintenanceEconomicReportQuery
  {
    return new ReadMaintenanceEconomicReportQuery('actor-a', 'organization-a', '2026-01-01', '2026-12-31', $groupBy, $siteId, $customerId, $equipmentId, $page, $itemsPerPage);
  }

  /**
   * Method procurement
   *
   * @access private
   *
   * @return ProcurementEconomicOverview empty authorized purchase source
   */
  private function procurement(): ProcurementEconomicOverview
  {
    $zero = new ProcurementEconomicAmount('0.000000', '0.000000', true);

    return new ProcurementEconomicOverview('organization-a', 'EUR', '2026-01-01T00:00:00Z', '2027-01-01T00:00:00Z', 0, 0, 0, $zero, $zero, $zero, $zero);
  }

  /**
   * Method row
   *
   * @access private
   *
   * @param MaintenanceEconomicReport $report actual allocated report
   * @param string $id selected row identity
   *
   * @return MaintenanceEconomicRow matched destination
   */
  private function row(MaintenanceEconomicReport $report, string $id): MaintenanceEconomicRow
  {
    foreach ($report->rows as $row) {
      if ($row->id === $id) {
        return $row;
      }
    }
    self::fail('Missing economic destination ' . $id);
  }

  /**
   * Method assertConflict
   *
   * @access private
   *
   * @param InterventionEconomicContext $context potentially foreign source
   * @param MaintenanceCostView $view potentially conflicting finance facts
   *
   * @return void
   */
  private function assertConflict(InterventionEconomicContext $context, MaintenanceCostView $view): void
  {
    try {
      new MaintenanceEconomicAggregator()->report($this->query(), 'EUR', [$context], [$context->id => $view], $this->procurement());
      self::fail('Conflicting economic source was accepted.');
    } catch (MaintenanceCostException $error) {
      self::assertSame('maintenance_cost_conflict', $error->reason);
    }
  }
  // #endregion
}
