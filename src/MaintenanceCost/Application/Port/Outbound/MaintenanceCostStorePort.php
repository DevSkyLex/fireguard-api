<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Port\Outbound;

use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostPlanning, MaintenanceCostSnapshot, MaintenanceExpense};

/** Interface MaintenanceCostStorePort. Stores only financial facts owned by this module. @category Port */
interface MaintenanceCostStorePort
{
  public function planning(string $organizationId, string $interventionId): MaintenanceCostPlanning;

  public function savePlanning(string $organizationId, string $interventionId, MaintenanceCostPlanning $planning): void;

  /**
   * @return list<MaintenanceExpense>
   */
  public function expenses(string $organizationId, string $interventionId): array;

  public function expenseByClientId(string $organizationId, string $clientId): ?MaintenanceExpense;

  public function lockExpenseIdentity(string $organizationId, string $clientId): void;

  public function expense(string $organizationId, string $id): ?MaintenanceExpense;

  public function saveExpense(MaintenanceExpense $expense): void;

  public function snapshot(string $organizationId, string $interventionId): ?MaintenanceCostSnapshot;

  public function saveSnapshot(string $organizationId, string $interventionId, MaintenanceCostSnapshot $snapshot): void;

  /**
   * Method economicInterventionIds
   *
   * Published allocations retain captured targets even after equipment moves or retirement.
   *
   * @access public
   *
   * @param string $organizationId authorized organization
   * @param ?string $siteId optional captured site filter
   * @param ?string $customerId optional captured client filter
   * @param ?string $equipmentId optional captured equipment filter
   *
   * @return list<string> at most 10000 candidate intervention identifiers, refusing oversized scopes
   */
  public function economicInterventionIds(string $organizationId, ?string $siteId, ?string $customerId, ?string $equipmentId): array;
}
