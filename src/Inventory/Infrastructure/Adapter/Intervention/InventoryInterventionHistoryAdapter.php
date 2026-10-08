<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Adapter\Intervention;

use Doctrine\DBAL\Connection;
use Inventory\Application\Port\Inbound\InventoryInterventionHistoryPort;
use LogicException;

/**
 * Class InventoryInterventionHistoryAdapter
 *
 * Queries only Inventory-owned references and shares its transaction-scoped work fence.
 *
 * @category Adapter
 */
final readonly class InventoryInterventionHistoryAdapter implements InventoryInterventionHistoryPort
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
   * Method lock
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $interventionId operational parent
   *
   * @return void
   */
  public function lock(string $organizationId, string $interventionId): void
  {
    if (!$this->connection->isTransactionActive()) {
      throw new LogicException('Inventory retention requires the main transaction.');
    }
    $this->connection->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'inventory-intervention:' . $organizationId . ':' . $interventionId]);
  }

  /**
   * Method hasHistory
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $interventionId operational parent
   * @param ?string $workItemId optional task scope
   *
   * @return bool whether retained declarations or movements exist
   */
  public function hasHistory(string $organizationId, string $interventionId, ?string $workItemId = null): bool
  {
    $parameters = ['organization' => $organizationId, 'intervention' => $interventionId];
    $scope = 'organization_id = :organization AND intervention_id = :intervention';
    if (null !== $workItemId) {
      $scope .= ' AND work_item_id = :item';
      $parameters['item'] = $workItemId;
    }

    return false !== $this->connection->fetchOne('SELECT 1 FROM inventory_declarations WHERE ' . $scope . ' UNION ALL SELECT 1 FROM inventory_movements WHERE ' . $scope . ' LIMIT 1', $parameters);
  }
  // #endregion
}
