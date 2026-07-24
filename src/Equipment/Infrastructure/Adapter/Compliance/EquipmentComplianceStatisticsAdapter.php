<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Compliance;

use Compliance\Application\Port\Outbound\EquipmentComplianceStatisticsPort;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Domain\ValueObject\EquipmentStatus;

/**
 * Adapter EquipmentComplianceStatisticsAdapter.
 *
 * Implements the Compliance module's equipment inventory statistics port by
 * querying `equipment` directly, grouped by facility — mirrors
 * `Equipment\Infrastructure\Adapter\Maintenance\EquipmentMaintenanceDirectoryAdapter`'s
 * choice to read the record directly rather than growing
 * `EquipmentRepositoryPort` for a cross-module aggregate with no equivalent
 * there. Only PUBLISHED equipment is counted, mirroring
 * `findPublishedById`'s visibility rule (draft intervention scratchpads are
 * invisible).
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentComplianceStatisticsAdapter implements EquipmentComplianceStatisticsPort
{
  // #region Constants
  /**
   * Constant UNASSIGNED_FACILITY_KEY.
   *
   * Must match `Compliance\Application\Service\ComplianceRegisterAggregator::UNASSIGNED_FACILITY_KEY`.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string UNASSIGNED_FACILITY_KEY = 'unassigned';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the main entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  public function equipmentInventoryByFacility(string $organizationId): array
  {
    $sql = <<<'SQL'
        SELECT
          COALESCE(facility_id, :unassignedKey) AS facility_key,
          COUNT(id) AS total_count,
          SUM(CASE WHEN status != :decommissionedStatus THEN 1 ELSE 0 END) AS active_count
        FROM equipment
        WHERE organization_id = :organizationId
          AND record_status = :publishedStatus
        GROUP BY facility_key
      SQL;

    /** @var list<array{facility_key: string, total_count: int|string, active_count: int|string|null}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, [
      'unassignedKey' => self::UNASSIGNED_FACILITY_KEY,
      'organizationId' => $organizationId,
      'decommissionedStatus' => EquipmentStatus::DECOMMISSIONED->value,
      'publishedStatus' => 'published',
    ])->fetchAllAssociative();

    $inventory = [];
    foreach ($rows as $row) {
      $inventory[$row['facility_key']] = [
        'total' => (int) $row['total_count'],
        'active' => (int) ($row['active_count'] ?? 0),
      ];
    }

    return $inventory;
  }
  // #endregion
}
