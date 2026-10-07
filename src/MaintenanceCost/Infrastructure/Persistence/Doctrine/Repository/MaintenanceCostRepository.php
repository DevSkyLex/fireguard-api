<?php

declare(strict_types=1);

namespace MaintenanceCost\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostItem, MaintenanceCostPlanning, MaintenanceCostSnapshot, MaintenanceCostTotals, MaintenanceExpense};
use MaintenanceCost\Application\Port\Outbound\MaintenanceCostStorePort;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Infrastructure\Persistence\Doctrine\Record\{MaintenanceCostPlanningRecord, MaintenanceCostSnapshotRecord, MaintenanceExpenseRecord};

use function count;
use function implode;

/** Class MaintenanceCostRepository. Owning main persistence, bounded fact lists and immutable snapshots. @category Repository */
final readonly class MaintenanceCostRepository implements MaintenanceCostStorePort
{
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  public function planning(string $organizationId, string $interventionId): MaintenanceCostPlanning
  {
    $record = $this->entityManager->find(MaintenanceCostPlanningRecord::class, ['organizationId' => $organizationId, 'interventionId' => $interventionId]);

    return $record instanceof MaintenanceCostPlanningRecord ? new MaintenanceCostPlanning($record->plannedBudget, $record->estimatedMinutes, $record->resources, $record->revision) : new MaintenanceCostPlanning();
  }

  public function savePlanning(string $organizationId, string $interventionId, MaintenanceCostPlanning $planning): void
  {
    $record = $this->entityManager->find(MaintenanceCostPlanningRecord::class, ['organizationId' => $organizationId, 'interventionId' => $interventionId]);
    if (!$record instanceof MaintenanceCostPlanningRecord) {
      $record = new MaintenanceCostPlanningRecord();
      $record->organizationId = $organizationId;
      $record->interventionId = $interventionId;
      $this->entityManager->persist($record);
    }
    $record->plannedBudget = $planning->plannedBudget;
    $record->estimatedMinutes = $planning->estimatedMinutes;
    $record->resources = $planning->resources;
    $record->revision = $planning->revision;
    $this->entityManager->flush();
  }

  public function expenses(string $organizationId, string $interventionId): array
  {
    $records = $this->entityManager->getRepository(MaintenanceExpenseRecord::class)->findBy(['organizationId' => $organizationId, 'interventionId' => $interventionId], ['incurredAt' => 'ASC', 'id' => 'ASC'], 10001);
    if (count($records) > 10000) {
      throw MaintenanceCostException::conflict('Cost facts exceed the bounded calculation size.');
    }
    $expenses = [];
    foreach ($records as $record) {
      $expenses[] = $this->expenseView($record);
    }

    return $expenses;
  }

  public function expenseByClientId(string $organizationId, string $clientId): ?MaintenanceExpense
  {
    $record = $this->entityManager->getRepository(MaintenanceExpenseRecord::class)->findOneBy(['organizationId' => $organizationId, 'clientId' => $clientId]);

    return $record instanceof MaintenanceExpenseRecord ? $this->expenseView($record) : null;
  }

  public function lockExpenseIdentity(string $organizationId, string $clientId): void
  {
    if (!$this->entityManager->getConnection()->isTransactionActive()) {
      throw MaintenanceCostException::conflict('Expense declarations require the main transaction.');
    }
    $this->entityManager->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'maintenance-expense:' . $organizationId . ':' . $clientId]);
  }

  public function expense(string $organizationId, string $id): ?MaintenanceExpense
  {
    $record = $this->entityManager->getRepository(MaintenanceExpenseRecord::class)->findOneBy(['organizationId' => $organizationId, 'id' => $id]);

    return $record instanceof MaintenanceExpenseRecord ? $this->expenseView($record) : null;
  }

  public function saveExpense(MaintenanceExpense $expense): void
  {
    $record = new MaintenanceExpenseRecord();
    foreach (['id', 'organizationId', 'interventionId', 'workItemId', 'clientId', 'amount', 'currency', 'description', 'incurredAt', 'adjustmentOf', 'createdBy', 'payloadHash', 'createdAt'] as $property) {
      $record->{$property} = $expense->{$property};
    }
    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  public function snapshot(string $organizationId, string $interventionId): ?MaintenanceCostSnapshot
  {
    $record = $this->entityManager->find(MaintenanceCostSnapshotRecord::class, ['organizationId' => $organizationId, 'interventionId' => $interventionId]);
    if (!$record instanceof MaintenanceCostSnapshotRecord) {
      return null;
    }
    $items = [];
    foreach ($record->items as $item) {
      $items[] = new MaintenanceCostItem($item['id'], $item['kind'], $item['workItemId'], $item['sourceId'], $item['sourceRevision'], $item['amount'], $item['currency'], $item['description'], $item['occurredAt'], $item['correctionOf'], $item['hourlyAmount'], $item['rateId'], $item['equipmentId'] ?? null, $item['allocation'] ?? null);
    }

    return new MaintenanceCostSnapshot($record->version, $record->capturedAt, $record->publicationId, $record->interventionRevision, $record->currency, new MaintenanceCostTotals($record->total, $record->knownTotal, $record->complete, $items), new MaintenanceCostPlanning($record->plannedBudget, $record->estimatedMinutes, $record->planningResources, $record->planningRevision));
  }

  public function saveSnapshot(string $organizationId, string $interventionId, MaintenanceCostSnapshot $snapshot): void
  {
    $existing = $this->snapshot($organizationId, $interventionId);
    if (null !== $existing) {
      if ($existing->publicationId !== $snapshot->publicationId) {
        throw MaintenanceCostException::conflict('A published cost snapshot cannot be replaced.');
      }

      return;
    }
    $record = new MaintenanceCostSnapshotRecord();
    $record->organizationId = $organizationId;
    $record->interventionId = $interventionId;
    $record->publicationId = $snapshot->publicationId;
    $record->interventionRevision = $snapshot->interventionRevision;
    $record->capturedAt = $snapshot->capturedAt;
    $record->version = $snapshot->version;
    $record->currency = $snapshot->currency;
    $record->total = $snapshot->totals->total;
    $record->knownTotal = $snapshot->totals->knownTotal;
    $record->complete = $snapshot->totals->complete;
    $planning = $snapshot->planning ?? new MaintenanceCostPlanning();
    $record->plannedBudget = $planning->plannedBudget;
    $record->estimatedMinutes = $planning->estimatedMinutes;
    $record->planningResources = $planning->resources;
    $record->planningRevision = $planning->revision;
    foreach ($snapshot->totals->items as $item) {
      $record->items[] = $item->toArray();
    }
    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  /**
   * Method economicInterventionIds
   *
   * Applies all target filters to one captured contribution before returning bounded owned identities.
   *
   * @access public
   *
   * @param string $organizationId authorized organization
   * @param ?string $siteId captured site filter
   * @param ?string $customerId captured client filter
   * @param ?string $equipmentId captured equipment filter
   *
   * @return list<string> candidate intervention identifiers
   */
  public function economicInterventionIds(string $organizationId, ?string $siteId, ?string $customerId, ?string $equipmentId): array
  {
    $targets = [];
    $parameters = ['organization' => $organizationId];
    foreach (['site' => $siteId, 'customer' => $customerId, 'equipment' => $equipmentId] as $target => $identifier) {
      if (null !== $identifier) {
        $targets[] = "j -> 'allocation' -> '" . $target . "' ->> 'id' = :" . $target;
        $parameters[$target] = $identifier;
      }
    }
    if ([] === $targets) {
      return [];
    }
    /** @var list<string> $ids */
    $ids = $this->entityManager->getConnection()->fetchFirstColumn('SELECT intervention_id FROM maintenance_cost_snapshots WHERE organization_id = :organization AND EXISTS (SELECT 1 FROM jsonb_array_elements(items::jsonb) j WHERE ' . implode(' AND ', $targets) . ') ORDER BY intervention_id LIMIT 10001', $parameters);
    if (count($ids) > 10000) {
      throw MaintenanceCostException::invalid('The captured financial scope exceeds 10000 interventions; narrow its target filters.');
    }

    return $ids;
  }

  private function expenseView(MaintenanceExpenseRecord $record): MaintenanceExpense
  {
    return new MaintenanceExpense($record->id, $record->organizationId, $record->interventionId, $record->workItemId, $record->clientId, $record->amount, $record->currency, $record->description, $record->incurredAt, $record->adjustmentOf, $record->createdBy, $record->payloadHash, $record->createdAt);
  }
}
