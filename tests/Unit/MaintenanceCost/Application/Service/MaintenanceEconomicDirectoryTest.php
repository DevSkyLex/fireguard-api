<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Application\Service;

use Intervention\Application\Port\Outbound\{InterventionEconomicScopePort, InterventionEquipmentSnapshotPort};
use Inventory\Application\Port\Inbound\InventoryInterventionResourcesPort;
use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostItem, MaintenanceCostPlanning, MaintenanceCostSnapshot, MaintenanceCostTotals, MaintenanceCostView};
use MaintenanceCost\Application\Port\Inbound\MaintenanceCostReadPort;
use MaintenanceCost\Application\Port\Outbound\MaintenanceCostStorePort;
use MaintenanceCost\Application\Service\MaintenanceEconomicDirectory;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

use function array_fill;
use function array_map;
use function range;

/**
 * Class MaintenanceEconomicDirectoryTest
 *
 * Verifies directory targets against private current and captured allocations before pagination.
 *
 * @category Test
 */
final class MaintenanceEconomicDirectoryTest extends TestCase
{
  private MaintenanceCostReadPort $costs;

  /**
   * @var MaintenanceCostStorePort&Stub
   */
  private MaintenanceCostStorePort $store;

  /**
   * @var InventoryInterventionResourcesPort&Stub
   */
  private InventoryInterventionResourcesPort $inventory;

  private InterventionEquipmentSnapshotPort $equipment;

  private InterventionEconomicScopePort $scopes;

  protected function setUp(): void
  {
    $this->costs = $this->createStub(MaintenanceCostReadPort::class);
    $this->store = $this->createStub(MaintenanceCostStorePort::class);
    $this->inventory = $this->createStub(InventoryInterventionResourcesPort::class);
    $this->equipment = $this->createStub(InterventionEquipmentSnapshotPort::class);
    $this->scopes = $this->createStub(InterventionEconomicScopePort::class);
  }

  public function testContributionLimitAccumulatesAcrossDossiersEvenAfterAMatch(): void
  {
    $this->store = $this->createMock(MaintenanceCostStorePort::class);
    $this->store->expects(self::once())->method('economicInterventionIds')->with('org', null, null, 'equipment')->willReturn(['first', 'second', 'unread']);
    $this->inventory = $this->createMock(InventoryInterventionResourcesPort::class);
    $this->inventory->expects(self::once())->method('economicInterventionIds')->with('org', ['equipment'])->willReturn([]);
    $matching = $this->item($this->allocation());
    $unallocated = $this->item(null);
    $this->costs = $this->createMock(MaintenanceCostReadPort::class);
    $this->costs->expects(self::exactly(2))->method('view')->willReturnCallback(
      function (string $organizationId, string $interventionId) use ($matching, $unallocated): MaintenanceCostView {
        self::assertSame('org', $organizationId);
        self::assertContains($interventionId, ['first', 'second']);

        return $this->view(
          'first' === $interventionId ? [$matching, ...array_fill(0, 24999, $unallocated)] : array_fill(0, 25001, $unallocated),
          interventionId: $interventionId,
        );
      },
    );

    try {
      $this->directory()->matchingIds('org', null, null, 'equipment');
      self::fail('An aggregate financial directory contribution scope was accepted.');
    } catch (MaintenanceCostException $error) {
      self::assertSame('maintenance_cost_invalid', $error->reason);
      self::assertSame('The financial directory scope exceeds 50000 contributions; narrow its target filters.', $error->getMessage());
    }
  }

  // #region Methods
  public function testUnfilteredSelectionDoesNotReadCandidatesOrFinancialSources(): void
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

    self::assertSame([], new MaintenanceEconomicDirectory($costs, $store, $inventory, $equipment, $scopes)->matchingIds('org', null, null, null));
  }

  public function testDirectMaterialMatchesWithoutAWorkItemAndDuplicateCandidatesAreReadOnce(): void
  {
    $this->store = $this->createMock(MaintenanceCostStorePort::class);
    $this->store->expects(self::once())->method('economicInterventionIds')->with('org', null, null, 'equipment')->willReturn(['work']);
    $this->inventory = $this->createMock(InventoryInterventionResourcesPort::class);
    $this->inventory->expects(self::once())->method('economicInterventionIds')->with('org', ['equipment'])->willReturn(['work', 'work']);
    $this->costs = $this->createMock(MaintenanceCostReadPort::class);
    $this->costs->expects(self::once())->method('view')->with('org', 'work')->willReturn($this->view([
      $this->item($this->allocation(), 'material'),
    ]));
    $this->expectNoCurrentScopeLookup();

    self::assertSame(['work'], $this->directory()->matchingIds('org', null, null, 'equipment'));
  }

  public function testEquipmentExposesOnlyMinimalCurrentAndFrozenAllocationIdentities(): void
  {
    $live = $this->allocation();
    $captured = $this->allocation('original-site', 'original-client', state: 'captured');
    $captured['equipment'] = ['id' => 'equipment', 'name' => 'Original asset', 'assetReference' => 'ORIGINAL'];
    $unknown = $this->allocation(null, null, 'historic-equipment', 'incomplete');
    $unknown['equipment'] = ['id' => 'historic-equipment', 'name' => null, 'assetReference' => null];
    $global = ['identityState' => 'live', 'equipment' => null, 'site' => null, 'customer' => null];
    $this->costs = $this->createMock(MaintenanceCostReadPort::class);
    $this->costs->expects(self::once())->method('view')->with('org', 'work')->willReturn($this->view([
      $this->item($live),
      $this->item($global),
      $this->item(null),
    ], [$this->item($live), $this->item($captured), $this->item($unknown)]));

    self::assertSame([
      ['id' => 'equipment', 'name' => 'Asset', 'assetReference' => 'ASSET-01', 'site' => ['id' => 'site', 'name' => 'Site site'], 'customer' => ['id' => 'client', 'name' => 'Client client']],
      ['id' => 'equipment', 'name' => 'Original asset', 'assetReference' => 'ORIGINAL', 'site' => ['id' => 'original-site', 'name' => 'Site original-site'], 'customer' => ['id' => 'original-client', 'name' => 'Client original-client']],
      ['id' => 'historic-equipment', 'name' => null, 'assetReference' => null, 'site' => null, 'customer' => null],
    ], $this->directory()->equipment('org', 'work'));
  }

  public function testCapturedTargetStillMatchesWhenTheEquipmentHasMovedOutOfCurrentScope(): void
  {
    $this->scopes = $this->createMock(InterventionEconomicScopePort::class);
    $this->scopes->expects(self::once())->method('facilityIds')->with('org', 'original-site', 'original-client')->willReturn(['original-facility']);
    $this->equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $this->equipment->expects(self::once())->method('equipmentIdsInFacilities')->with('org', ['original-facility'])->willReturn([]);
    $this->store = $this->createMock(MaintenanceCostStorePort::class);
    $this->store->expects(self::once())->method('economicInterventionIds')->with('org', 'original-site', 'original-client', 'equipment')->willReturn(['work']);
    $this->inventory = $this->createMock(InventoryInterventionResourcesPort::class);
    $this->inventory->expects(self::once())->method('economicInterventionIds')->with('org', [])->willReturn([]);
    $this->costs = $this->createMock(MaintenanceCostReadPort::class);
    $this->costs->expects(self::once())->method('view')->with('org', 'work')->willReturn($this->view([
      $this->item($this->allocation('current-site', 'current-client')),
    ], [$this->item($this->allocation('original-site', 'original-client', state: 'captured'))]));

    self::assertSame(['work'], $this->directory()->matchingIds('org', 'original-site', 'original-client', 'equipment'));
  }

  #[DataProvider('historicTargetCases')]
  public function testUnknownHistoricSiteOrClientIsNotInferredFromCandidateReferences(?string $siteId, ?string $customerId): void
  {
    $this->scopes = $this->createMock(InterventionEconomicScopePort::class);
    $this->scopes->expects(self::once())->method('facilityIds')->with('org', $siteId, $customerId)->willReturn(['current-facility']);
    $this->equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $this->equipment->expects(self::once())->method('equipmentIdsInFacilities')->with('org', ['current-facility'])->willReturn(['equipment']);
    $this->store = $this->createMock(MaintenanceCostStorePort::class);
    $this->store->expects(self::once())->method('economicInterventionIds')->with('org', $siteId, $customerId, 'equipment')->willReturn(['work']);
    $this->inventory = $this->createMock(InventoryInterventionResourcesPort::class);
    $this->inventory->expects(self::once())->method('economicInterventionIds')->with('org', ['equipment'])->willReturn(['work']);
    $unknown = $this->item($this->allocation(null, null, state: 'incomplete'), 'material');
    $this->costs = $this->createMock(MaintenanceCostReadPort::class);
    $this->costs->expects(self::once())->method('view')->with('org', 'work')->willReturn($this->view([$unknown], [$unknown]));

    self::assertSame([], $this->directory()->matchingIds('org', $siteId, $customerId, 'equipment'));
  }

  /**
   * @return iterable<string,array{?string,?string}> missing historical scope dimensions
   */
  public static function historicTargetCases(): iterable
  {
    yield 'site' => ['site', null];
    yield 'client' => [null, 'client'];
    yield 'site and client' => ['site', 'client'];
  }

  #[DataProvider('combinedTargetCases')]
  public function testAllCombinedFiltersMustMatchTheSameContribution(bool $matchingContribution): void
  {
    $this->scopes = $this->createMock(InterventionEconomicScopePort::class);
    $this->scopes->expects(self::once())->method('facilityIds')->with('org', 'site', 'client')->willReturn(['facility']);
    $this->equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $this->equipment->expects(self::once())->method('equipmentIdsInFacilities')->with('org', ['facility'])->willReturn(['equipment']);
    $this->store = $this->createMock(MaintenanceCostStorePort::class);
    $this->store->expects(self::once())->method('economicInterventionIds')->with('org', 'site', 'client', 'equipment')->willReturn(['work']);
    $this->inventory = $this->createMock(InventoryInterventionResourcesPort::class);
    $this->inventory->expects(self::once())->method('economicInterventionIds')->with('org', ['equipment'])->willReturn([]);
    $items = [
      $this->item($this->allocation('site', 'other-client')),
      $this->item($this->allocation('other-site', 'client')),
      $this->item($this->allocation('site', 'client', 'other-equipment')),
    ];
    if ($matchingContribution) {
      $items[] = $this->item($this->allocation());
    }
    $this->costs = $this->createMock(MaintenanceCostReadPort::class);
    $this->costs->expects(self::once())->method('view')->with('org', 'work')->willReturn($this->view($items));

    self::assertSame($matchingContribution ? ['work'] : [], $this->directory()->matchingIds('org', 'site', 'client', 'equipment'));
  }

  /**
   * @return iterable<string,array{bool}> independent targets versus one matching allocation
   */
  public static function combinedTargetCases(): iterable
  {
    yield 'different contributions cannot combine' => [false];
    yield 'one contribution satisfies every target' => [true];
  }

  #[DataProvider('foreignViewCases')]
  public function testForeignProjectionIsRejectedBeforeReturningMatchesOrIdentities(string $operation, string $organizationId, string $interventionId): void
  {
    $this->store->method('economicInterventionIds')->willReturn(['work']);
    $this->inventory->method('economicInterventionIds')->willReturn([]);
    $this->costs = $this->createMock(MaintenanceCostReadPort::class);
    $this->costs->expects(self::once())->method('view')->with('org', 'work')->willReturn($this->view([
      $this->item($this->allocation()),
    ], organizationId: $organizationId, interventionId: $interventionId));

    try {
      if ('matchingIds' === $operation) {
        $this->directory()->matchingIds('org', null, null, 'equipment');
      } else {
        $this->directory()->equipment('org', 'work');
      }
      self::fail('A foreign financial directory projection was accepted.');
    } catch (MaintenanceCostException $error) {
      self::assertSame('maintenance_cost_conflict', $error->reason);
      self::assertSame('The financial directory source is outside the authorized organization.', $error->getMessage());
    }
  }

  /**
   * @return iterable<string,array{string,string,string}> source scope mismatches for both public operations
   */
  public static function foreignViewCases(): iterable
  {
    yield 'matching foreign organization' => ['matchingIds', 'foreign-org', 'work'];
    yield 'matching foreign intervention' => ['matchingIds', 'org', 'foreign-work'];
    yield 'identities foreign organization' => ['equipment', 'foreign-org', 'work'];
    yield 'identities foreign intervention' => ['equipment', 'org', 'foreign-work'];
  }

  public function testMoreThanTenThousandCandidatesIsRejectedBeforeAnyFinancialRead(): void
  {
    $candidates = array_map(static fn (int $index): string => 'work-' . $index, range(1, 10001));
    $this->store = $this->createMock(MaintenanceCostStorePort::class);
    $this->store->expects(self::once())->method('economicInterventionIds')->with('org', null, null, 'equipment')->willReturn($candidates);
    $this->inventory = $this->createMock(InventoryInterventionResourcesPort::class);
    $this->inventory->expects(self::once())->method('economicInterventionIds')->with('org', ['equipment'])->willReturn([]);
    $this->costs = $this->createMock(MaintenanceCostReadPort::class);
    $this->costs->expects(self::never())->method('view');

    try {
      $this->directory()->matchingIds('org', null, null, 'equipment');
      self::fail('An oversized financial directory candidate scope was accepted.');
    } catch (MaintenanceCostException $error) {
      self::assertSame('maintenance_cost_invalid', $error->reason);
      self::assertSame('The financial directory scope exceeds 10000 interventions; narrow its target filters.', $error->getMessage());
    }
  }

  public function testExactlyTenThousandUniqueCandidatesRemainsAcceptedAfterDeduplication(): void
  {
    $candidates = array_map(static fn (int $index): string => 'work-' . $index, range(1, 10000));
    $this->store = $this->createMock(MaintenanceCostStorePort::class);
    $this->store->expects(self::once())->method('economicInterventionIds')->with('org', null, null, 'equipment')->willReturn($candidates);
    $this->inventory = $this->createMock(InventoryInterventionResourcesPort::class);
    $this->inventory->expects(self::once())->method('economicInterventionIds')->with('org', ['equipment'])->willReturn($candidates);
    $this->costs = $this->createMock(MaintenanceCostReadPort::class);
    $this->costs->expects(self::exactly(10000))->method('view')->with('org', self::anything())->willReturnCallback(
      fn (string $organizationId, string $interventionId): MaintenanceCostView => $this->view([], organizationId: $organizationId, interventionId: $interventionId),
    );

    self::assertSame([], $this->directory()->matchingIds('org', null, null, 'equipment'));
  }

  #[DataProvider('contributionLimitCases')]
  public function testCurrentAndFrozenContributionsShareOneExplicitCalculationLimit(int $frozenCount, bool $overLimit): void
  {
    $this->store->method('economicInterventionIds')->willReturn(['work']);
    $this->inventory->method('economicInterventionIds')->willReturn([]);
    $item = $this->item(null);
    $this->costs = $this->createMock(MaintenanceCostReadPort::class);
    $this->costs->expects(self::once())->method('view')->with('org', 'work')->willReturn(
      $this->view(array_fill(0, 25000, $item), array_fill(0, $frozenCount, $item)),
    );
    if (!$overLimit) {
      self::assertSame([], $this->directory()->matchingIds('org', null, null, 'equipment'));

      return;
    }

    try {
      $this->directory()->matchingIds('org', null, null, 'equipment');
      self::fail('An oversized financial directory contribution scope was accepted.');
    } catch (MaintenanceCostException $error) {
      self::assertSame('maintenance_cost_invalid', $error->reason);
      self::assertSame('The financial directory scope exceeds 50000 contributions; narrow its target filters.', $error->getMessage());
    }
  }

  /**
   * @return iterable<string,array{int,bool}> exact combined contribution boundary
   */
  public static function contributionLimitCases(): iterable
  {
    yield 'exactly fifty thousand' => [25000, false];
    yield 'fifty thousand and one' => [25001, true];
  }

  private function expectNoCurrentScopeLookup(): void
  {
    $this->scopes = $this->createMock(InterventionEconomicScopePort::class);
    $this->scopes->expects(self::never())->method('facilityIds');
    $this->equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $this->equipment->expects(self::never())->method('equipmentIdsInFacilities');
  }

  private function directory(): MaintenanceEconomicDirectory
  {
    return new MaintenanceEconomicDirectory($this->costs, $this->store, $this->inventory, $this->equipment, $this->scopes);
  }

  /**
   * @param ?string $siteId known root site identity
   * @param ?string $customerId known internal client identity
   * @param string $equipmentId known equipment identity
   * @param string $state current or captured provenance
   *
   * @return array{identityState:string,equipment:array{id:string,name:string,assetReference:string},site:?array{id:string,name:string},customer:?array{id:string,name:string}} allocation fixture
   */
  private function allocation(?string $siteId = 'site', ?string $customerId = 'client', string $equipmentId = 'equipment', string $state = 'live'): array
  {
    return [
      'identityState' => $state,
      'equipment' => ['id' => $equipmentId, 'name' => 'Asset', 'assetReference' => 'ASSET-01'],
      'site' => null === $siteId ? null : ['id' => $siteId, 'name' => 'Site ' . $siteId],
      'customer' => null === $customerId ? null : ['id' => $customerId, 'name' => 'Client ' . $customerId],
    ];
  }

  /**
   * @param ?array{identityState:string,equipment:?array{id:string,name:?string,assetReference:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}} $allocation verified target identities or legacy absence
   * @param string $kind financial source category
   *
   * @return MaintenanceCostItem private source carrying no work item
   */
  private function item(?array $allocation, string $kind = 'expense'): MaintenanceCostItem
  {
    return new MaintenanceCostItem('fact', $kind, null, 'source', null, '0.000000', 'EUR', 'Private contribution body', '2026-10-07T12:00:00Z', equipmentId: $allocation['equipment']['id'] ?? null, allocation: $allocation);
  }

  /**
   * @param list<MaintenanceCostItem> $current current financial facts
   * @param ?list<MaintenanceCostItem> $frozen original publication facts
   * @param string $organizationId source owner
   * @param string $interventionId source dossier identity
   *
   * @return MaintenanceCostView bounded private projection
   */
  private function view(array $current, ?array $frozen = null, string $organizationId = 'org', string $interventionId = 'work'): MaintenanceCostView
  {
    $totals = new MaintenanceCostTotals('0.000000', '0.000000', true, $current);
    $snapshot = null === $frozen ? null : new MaintenanceCostSnapshot(1, '2026-10-07T12:00:00Z', 'publication', 1, 'EUR', new MaintenanceCostTotals('0.000000', '0.000000', true, $frozen));

    return new MaintenanceCostView($interventionId, $organizationId, 'EUR', new MaintenanceCostPlanning(), $totals, $snapshot);
  }
  // #endregion
}
