<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Idempotency;

use Compliance\Application\Port\Outbound\ComplianceExportEntitlementPort;
use Doctrine\DBAL\Connection;
use Equipment\Application\Port\Outbound\EquipmentReportEntitlementPort;
use Inspection\Application\Port\Outbound\InspectionReportEntitlementPort;

use function in_array;
use function is_string;

/**
 * Class FreshOrganizationExportEntitlementAdapter
 *
 * Resolves the current owner plan assignment and default without managed plan or organization caches.
 *
 * @category Adapter
 */
final readonly class FreshOrganizationExportEntitlementAdapter implements ComplianceExportEntitlementPort, EquipmentReportEntitlementPort, InspectionReportEntitlementPort
{
  // #region Constructor
  /**
   * @param Connection $connection explicitly selected main connection
   */
  public function __construct(private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function isExportEntitled(string $organizationId): bool
  {
    return in_array($this->resolvePlanKey($organizationId), ['pro', 'max'], true);
  }

  /**
   * {@inheritdoc}
   */
  public function resolvePlanKey(string $organizationId): ?string
  {
    $row = $this->connection->fetchAssociative('SELECT plan_id FROM organizations WHERE id = :id', ['id' => $organizationId]);
    if (false === $row) {
      return null;
    }
    $key = is_string($row['plan_id'] ?? null) ? $this->connection->fetchOne('SELECT plan_key FROM plans WHERE id = :id', ['id' => $row['plan_id']]) : false;
    if (!is_string($key)) {
      $key = $this->connection->fetchOne('SELECT plan_key FROM plans WHERE is_default = TRUE ORDER BY sort_order ASC LIMIT 1');
    }

    return is_string($key) ? $key : null;
  }
  // #endregion
}
