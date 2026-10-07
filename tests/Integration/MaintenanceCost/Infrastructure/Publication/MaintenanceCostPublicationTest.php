<?php

declare(strict_types=1);

namespace Tests\Integration\MaintenanceCost\Infrastructure\Publication;

use Customer\Infrastructure\Persistence\Doctrine\Record\CustomerRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Application\Port\Outbound\{InterventionSiteCustomerSnapshotPort, InterventionTimeEntryRepositoryPort};
use Intervention\Domain\Model\TimeEntry\TimeEntry;
use Intervention\Infrastructure\Adapter\Publication\DoctrinePublicationAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionWorkItemRecord};
use Inventory\Application\Contract\Stock\InventoryPublicationBlockedException;
use Inventory\Application\Port\Outbound\InventoryStorePort;
use Inventory\Domain\Model\Stock\ConsumptionDeclaration;
use Inventory\Infrastructure\Persistence\Doctrine\Record\InventoryMovementRecord;
use MaintenanceCost\Application\Contract\Cost\MaintenanceCostPlanning;
use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;
use MaintenanceCost\Application\Port\Outbound\MaintenanceCostStorePort;
use MaintenanceCost\Application\Port\Outbound\Rate\MaintenanceRateStorePort;
use MaintenanceCost\Application\Service\MaintenanceCostProjection;
use MaintenanceCost\Infrastructure\Persistence\Doctrine\Record\MaintenanceExpenseRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function is_int;

/** Class MaintenanceCostPublicationTest. Real main publication freezes private costs while later owner facts remain visible. @category IntegrationTest */
final class MaintenanceCostPublicationTest extends KernelTestCase
{
  private const string ORG = '980e8400-e29b-41d4-a716-449130000001';

  private const string ORDER = '980e8400-e29b-41d4-a716-449130000002';

  private const string TASK = '980e8400-e29b-41d4-a716-449130000003';

  private const string MEMBER = '980e8400-e29b-41d4-a716-449130000004';

  private const string ENTRY = '980e8400-e29b-41d4-a716-449130000005';

  private const string PUBLICATION = '980e8400-e29b-41d4-a716-449130000006';

  private const string MOVEMENT = '980e8400-e29b-41d4-a716-449130000007';

  private const string PART = '980e8400-e29b-41d4-a716-449130000008';

  private const string WAREHOUSE = '980e8400-e29b-41d4-a716-449130000009';

  public function testPublicationRetainsAnExactAggregateLargerThanIndividualExpensePrecision(): void
  {
    $manager = $this->fixture(true);
    foreach (['980e8400-e29b-41d4-a716-449130000090', '980e8400-e29b-41d4-a716-449130000091'] as $id) {
      $expense = new MaintenanceExpenseRecord();
      $expense->id = $id;
      $expense->organizationId = self::ORG;
      $expense->interventionId = self::ORDER;
      $expense->clientId = $id;
      $expense->amount = '999999999999999999.000000';
      $expense->currency = 'EUR';
      $expense->description = 'Large exact validated external resource';
      $expense->createdBy = self::MEMBER;
      $expense->payloadHash = 'publication-regression';
      $expense->incurredAt = $expense->createdAt = new DateTimeImmutable('2026-10-01T12:00:00Z');
      $manager->persist($expense);
    }
    $manager->flush();
    self::assertTrue($this->publisher()->publish(self::PUBLICATION));
    $snapshot = $this->costs()->snapshot(self::ORG, self::ORDER);
    self::assertNotNull($snapshot);
    self::assertSame('2000000000000000048.000000', $snapshot->totals->total);
    self::assertSame('2000000000000000048.000000', $snapshot->totals->knownTotal);
    self::assertTrue($snapshot->totals->complete);
    self::assertSame($snapshot->totals->total, $this->projection()->view(self::ORG, self::ORDER)->current->total);
  }

  public function testDirectMaterialIdentityIsFrozenAndItsLaterReturnUsesTheOriginalClient(): void
  {
    $manager = $this->fixture(true);
    $now = new DateTimeImmutable('2026-10-01T12:00:00Z');
    $customer = new CustomerRecord();
    $customer->id = '980e8400-e29b-41d4-a716-449130000070';
    $customer->organizationId = self::ORG;
    $customer->name = 'Original client';
    $customer->createdAt = $customer->updatedAt = $now;
    $manager->persist($customer);
    $site = new FacilityRecord();
    $site->id = '980e8400-e29b-41d4-a716-449130000071';
    $site->organization = $manager->getReference(OrganizationRecord::class, self::ORG);
    $site->type = 'site';
    $site->name = 'Original site';
    $site->customerId = $customer->id;
    $site->createdAt = $site->updatedAt = $now;
    $manager->persist($site);
    $equipment = new EquipmentRecord();
    $equipment->id = '980e8400-e29b-41d4-a716-449130000072';
    $equipment->organization = $site->organization;
    $equipment->facilityId = $site->id;
    $equipment->type = 'extinguisher';
    $equipment->name = 'Original asset';
    $equipment->createdAt = $equipment->updatedAt = $now;
    $manager->persist($equipment);
    $this->movement($manager, self::MOVEMENT, '-1.000000', '-10.000000');
    $manager->flush();
    $movement = $manager->find(InventoryMovementRecord::class, self::MOVEMENT);
    self::assertInstanceOf(InventoryMovementRecord::class, $movement);
    $movement->workItemId = null;
    $movement->equipmentId = $equipment->id;
    $manager->flush();
    self::assertTrue($this->publisher()->publish(self::PUBLICATION));
    $snapshot = $this->costs()->snapshot(self::ORG, self::ORDER);
    self::assertNotNull($snapshot);
    $original = $snapshot->totals->items[1];
    self::assertNotNull($original->allocation);
    self::assertSame('captured', $original->allocation['identityState']);
    self::assertSame(['id' => $site->id, 'name' => 'Original site'], $original->allocation['site']);
    self::assertSame(['id' => $customer->id, 'name' => 'Original client'], $original->allocation['customer']);
    $customer->name = 'Current renamed client';
    $site->name = 'Current renamed site';
    $equipment->name = 'Current renamed asset';
    $return = new InventoryMovementRecord();
    $return->id = '980e8400-e29b-41d4-a716-449130000073';
    $return->organizationId = self::ORG;
    $return->warehouseId = self::WAREHOUSE;
    $return->partId = self::PART;
    $return->kind = 'return';
    $return->quantity = '0.500000';
    $return->unitCost = '10.000000';
    $return->totalValue = '5.000000';
    $return->currency = 'EUR';
    $return->reason = 'Unused part returned after publication';
    $return->actorId = self::MEMBER;
    $return->occurredAt = new DateTimeImmutable('2026-10-02T12:00:00Z');
    $return->interventionId = self::ORDER;
    $return->equipmentId = $equipment->id;
    $return->correctionOf = self::MOVEMENT;
    $return->late = true;
    $manager->persist($return);
    $manager->flush();
    $current = $this->projection()->view(self::ORG, self::ORDER);
    self::assertSame('55.000000', $current->current->total);
    self::assertSame('-5.000000', $current->current->items[2]->amount);
    self::assertSame($original->allocation, $current->current->items[2]->allocation);
    self::assertSame('60.000000', $current->frozen?->totals->total);
    self::assertSame($original->allocation, $this->costs()->snapshot(self::ORG, self::ORDER)?->totals->items[1]->allocation);
  }

  public function testPublicationFreezesTimeMaterialsAndBudgetThenCorrectionsOnlyChangeCurrentCosts(): void
  {
    $manager = $this->fixture(true);
    $costs = $this->costs();
    $costs->savePlanning(self::ORG, self::ORDER, new MaintenanceCostPlanning('200.000000', 60, [], 1));
    $this->movement($manager, self::MOVEMENT, '-1.000000', '-10.000000');
    $manager->flush();
    $publication = $this->publisher();
    self::assertTrue($publication->publish(self::PUBLICATION));
    $frozen = $costs->snapshot(self::ORG, self::ORDER);
    self::assertNotNull($frozen);
    self::assertSame('60.000000', $frozen->totals->total);
    self::assertSame('200.000000', $frozen->planning?->plannedBudget);
    self::assertCount(2, $frozen->totals->items);
    $dossier = $manager->getConnection()->fetchOne('SELECT closure_snapshot FROM interventions WHERE id = ?', [self::ORDER]);
    self::assertIsString($dossier);
    self::assertStringNotContainsString('hourlyAmount', $dossier);
    self::assertStringNotContainsString('knownTotal', $dossier);
    self::assertStringNotContainsString('plannedBudget', $dossier);

    /** @var InterventionTimeEntryRepositoryPort $times */
    $times = self::getContainer()->get(InterventionTimeEntryRepositoryPort::class);
    $corrected = new TimeEntry(self::ENTRY, self::TASK, self::MEMBER, '2026-10-01', 30, 'Inventory reconciliation')->correct(1, '2026-10-01', 60, 'Correction to one hour');
    $times->save($corrected, self::ORG, self::MEMBER);
    $this->movement($manager, '980e8400-e29b-41d4-a716-449130000010', '-1.000000', '-5.000000', true);
    $manager->flush();
    $current = $this->projection()->view(self::ORG, self::ORDER);
    self::assertSame('115.000000', $current->current->total);
    self::assertSame('60.000000', $current->frozen?->totals->total);
    self::assertSame('time:' . self::ENTRY . ':1', $current->current->items[0]->correctionOf);
    self::assertSame('publication:' . self::PUBLICATION, $current->current->items[2]->correctionOf);
    self::assertSame($dossier, $manager->getConnection()->fetchOne('SELECT closure_snapshot FROM interventions WHERE id = ?', [self::ORDER]));
    self::assertFalse($publication->publish(self::PUBLICATION));
    self::assertSame('60.000000', $costs->snapshot(self::ORG, self::ORDER)?->totals->total);
    self::assertSame('EUR', $manager->getConnection()->fetchOne('SELECT currency FROM maintenance_cost_currency_settings WHERE organization_id = ?', [self::ORG]));
  }

  public function testUnknownRateIsRetainedAsIncompleteAtClosure(): void
  {
    $this->fixture(false);
    self::assertTrue($this->publisher()->publish(self::PUBLICATION));
    $snapshot = $this->costs()->snapshot(self::ORG, self::ORDER);
    self::assertNotNull($snapshot);
    self::assertNull($snapshot->totals->total);
    self::assertSame('0.000000', $snapshot->totals->knownTotal);
    self::assertFalse($snapshot->totals->complete);
  }

  public function testReceivedUnresolvedStockDeclarationRollsBackPublicationAndSnapshot(): void
  {
    $manager = $this->fixture(true);
    /** @var InventoryStorePort $stock */
    $stock = self::getContainer()->get(InventoryStorePort::class);
    $stock->saveDeclaration(new ConsumptionDeclaration('980e8400-e29b-41d4-a716-449130000011', self::ORG, self::PART, self::WAREHOUSE, '2.000000', self::ORDER, self::TASK, null, self::MEMBER, new DateTimeImmutable('2026-10-01T12:00:00Z'), 'received_pending', 'insufficient_stock', null));

    try {
      $this->publisher()->publish(self::PUBLICATION);
      self::fail('An unresolved physical declaration must block publication.');
    } catch (InventoryPublicationBlockedException) {
      self::assertSame('submitted', $manager->getConnection()->fetchOne('SELECT status FROM interventions WHERE id = ?', [self::ORDER]));
      self::assertNull($manager->getConnection()->fetchOne('SELECT closure_snapshot FROM interventions WHERE id = ?', [self::ORDER]));
      self::assertSame(0, $this->countRows($manager, 'maintenance_cost_snapshots'));
      self::assertSame('received_pending', $manager->getConnection()->fetchOne('SELECT status FROM inventory_declarations WHERE id = ?', ['980e8400-e29b-41d4-a716-449130000011']));
    }
  }

  public function testFailureAfterCostCaptureRollsBackThePrivateSnapshotAndCurrencyLock(): void
  {
    $manager = $this->fixture(true, true);

    try {
      $this->publisher()->publish(self::PUBLICATION);
      self::fail('A failed dossier capture must roll back the whole publication.');
    } catch (RuntimeException $error) {
      self::assertSame('Dossier capture unavailable', $error->getMessage());
      self::assertSame('submitted', $manager->getConnection()->fetchOne('SELECT status FROM interventions WHERE id = ?', [self::ORDER]));
      self::assertSame(0, $this->countRows($manager, 'maintenance_cost_snapshots'));
      self::assertSame(0, $this->countRows($manager, 'maintenance_cost_currency_settings'));
    }
  }

  private function fixture(bool $rate, bool $snapshotFailure = false): EntityManagerInterface
  {
    self::bootKernel();
    if ($snapshotFailure) {
      $sites = $this->createStub(InterventionSiteCustomerSnapshotPort::class);
      $sites->method('snapshot')->willThrowException(new RuntimeException('Dossier capture unavailable'));
      self::getContainer()->set(InterventionSiteCustomerSnapshotPort::class, $sites);
    }
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $now = new DateTimeImmutable('2026-10-01T10:00:00Z');
    $organization = new OrganizationRecord();
    $organization->id = self::ORG;
    $organization->name = 'Private maintenance costing';
    $organization->slug = 'private-maintenance-costing';
    $organization->ownerUserId = self::MEMBER;
    $organization->createdByUserId = self::MEMBER;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $manager->persist($organization);
    $order = new InterventionRecord();
    $order->id = self::ORDER;
    $order->organization = $organization;
    $order->number = 1;
    $order->type = 'inventory';
    $order->name = 'Inventory and time reconciliation';
    $order->status = 'submitted';
    $order->priority = 'normal';
    $order->responsibleId = self::MEMBER;
    $order->revision = 1;
    $order->createdAt = $now;
    $order->updatedAt = $now;
    $manager->persist($order);
    $task = new InterventionWorkItemRecord();
    $task->id = self::TASK;
    $task->intervention = $order;
    $task->action = 'inventory';
    $task->target = 'Inventory reconciliation';
    $task->status = 'completed';
    $task->source = 'planned';
    $task->required = true;
    $task->createdAt = $now;
    $task->updatedAt = $now;
    $manager->persist($task);
    $manager->flush();
    /** @var InterventionTimeEntryRepositoryPort $times */
    $times = self::getContainer()->get(InterventionTimeEntryRepositoryPort::class);
    $times->save(new TimeEntry(self::ENTRY, self::TASK, self::MEMBER, '2026-10-01', 30, 'Inventory reconciliation'), self::ORG, self::MEMBER);
    if ($rate) {
      /** @var MaintenanceRateStorePort $rates */
      $rates = self::getContainer()->get(MaintenanceRateStorePort::class);
      $rates->synchronized(self::ORG, fn () => $rates->append(self::ORG, '980e8400-e29b-41d4-a716-449130000012', new MaintenanceRateSnapshot('980e8400-e29b-41d4-a716-449130000013', self::MEMBER, '100.000000', 'EUR', '2026-01-01')));
    }
    $this->publisher()->createOrGetPending(self::PUBLICATION, self::ORDER, 1);
    $this->publisher()->markProcessing(self::PUBLICATION);

    return $manager;
  }

  private function movement(EntityManagerInterface $manager, string $id, string $quantity, string $value, bool $late = false): void
  {
    $movement = new InventoryMovementRecord();
    $movement->id = $id;
    $movement->organizationId = self::ORG;
    $movement->warehouseId = self::WAREHOUSE;
    $movement->partId = self::PART;
    $movement->kind = 'consumption';
    $movement->quantity = $quantity;
    $movement->unitCost = $late ? '5.000000' : '10.000000';
    $movement->totalValue = $value;
    $movement->currency = 'EUR';
    $movement->reason = $late ? 'Delayed synchronized consumption' : 'Actual material consumption';
    $movement->actorId = self::MEMBER;
    $movement->occurredAt = new DateTimeImmutable('2026-10-01T12:00:00Z');
    $movement->interventionId = self::ORDER;
    $movement->workItemId = self::TASK;
    $movement->late = $late;
    $manager->persist($movement);
  }

  private function publisher(): DoctrinePublicationAdapter
  {
    /** @var DoctrinePublicationAdapter $publisher */
    $publisher = self::getContainer()->get(DoctrinePublicationAdapter::class);

    return $publisher;
  }

  private function countRows(EntityManagerInterface $manager, string $table): int
  {
    $value = $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table . ' WHERE organization_id = ?', [self::ORG]);
    if (is_int($value)) {
      return $value;
    }
    self::assertIsString($value);
    self::assertIsNumeric($value);

    return (int) $value;
  }

  private function costs(): MaintenanceCostStorePort
  {
    /** @var MaintenanceCostStorePort $costs */
    $costs = self::getContainer()->get(MaintenanceCostStorePort::class);

    return $costs;
  }

  private function projection(): MaintenanceCostProjection
  {
    /** @var MaintenanceCostProjection $projection */
    $projection = self::getContainer()->get(MaintenanceCostProjection::class);

    return $projection;
  }
}
