<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Intervention;

use Doctrine\DBAL\Connection;
use Maintenance\Application\Port\Inbound\MaintenanceInterventionHistoryPort;

/**
 * Class MaintenanceInterventionHistoryAdapter
 *
 * Keeps occurrence replay and retry references discoverable through an owner-published check.
 *
 * @category Adapter
 */
final readonly class MaintenanceInterventionHistoryAdapter implements MaintenanceInterventionHistoryPort
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
   *
   * @return bool whether a retained occurrence references this work
   */
  public function hasHistory(string $organizationId, string $interventionId): bool
  {
    return false !== $this->connection->fetchOne('SELECT 1 FROM maintenance_occurrences WHERE organization_id = :organization AND intervention_id = :intervention LIMIT 1', ['organization' => $organizationId, 'intervention' => $interventionId]);
  }
  // #endregion
}
