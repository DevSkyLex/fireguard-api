<?php

declare(strict_types=1);

namespace MaintenanceCost\Infrastructure\Adapter\Intervention;

use Doctrine\DBAL\Connection;
use MaintenanceCost\Application\Port\Inbound\MaintenanceCostInterventionHistoryPort;

/**
 * Class MaintenanceCostInterventionHistoryAdapter
 *
 * Answers bounded retention checks from financial tables without exposing private amounts.
 *
 * @category Adapter
 */
final readonly class MaintenanceCostInterventionHistoryAdapter implements MaintenanceCostInterventionHistoryPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param Connection $connection owning main connection
   *
   * @return void
   */
  public function __construct(private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method hasHistory
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $interventionId operational parent
   * @param ?string $workItemId optional task scope
   *
   * @return bool whether owned financial facts reference this work
   */
  public function hasHistory(string $organizationId, string $interventionId, ?string $workItemId = null): bool
  {
    $parameters = ['organization' => $organizationId, 'intervention' => $interventionId];
    $scope = 'organization_id = :organization AND intervention_id = :intervention';
    $expenseScope = $scope;
    $planningScope = $scope;
    $snapshotScope = $scope;
    if (null !== $workItemId) {
      $parameters['item'] = $workItemId;
      $expenseScope .= ' AND work_item_id = :item';
      $planningScope .= " AND EXISTS (SELECT 1 FROM jsonb_array_elements(resources::jsonb) r WHERE r->>'workItemId' = :item)";
      $snapshotScope .= " AND (EXISTS (SELECT 1 FROM jsonb_array_elements(items::jsonb) i WHERE i->>'workItemId' = :item) OR EXISTS (SELECT 1 FROM jsonb_array_elements(planning_resources::jsonb) r WHERE r->>'workItemId' = :item))";
    }

    return false !== $this->connection->fetchOne('SELECT 1 FROM maintenance_cost_expenses WHERE ' . $expenseScope . ' UNION ALL SELECT 1 FROM maintenance_cost_planning WHERE ' . $planningScope . ' UNION ALL SELECT 1 FROM maintenance_cost_snapshots WHERE ' . $snapshotScope . ' LIMIT 1', $parameters);
  }
  // #endregion
}
