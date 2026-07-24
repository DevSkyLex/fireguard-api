<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Compliance;

use Compliance\Application\Port\Outbound\InspectionComplianceStatisticsPort;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Domain\ValueObject\NonConformitySeverity;

/**
 * Adapter InspectionComplianceStatisticsAdapter.
 *
 * Implements the Compliance module's open-non-conformity statistics port by
 * querying `non_conformities` joined to `inspections` directly, grouped by
 * the inspection's `facility_id` — the same treatment
 * `MaintenanceComplianceStatisticsAdapter` gives its own cross-module read
 * model: the grouped-by-facility aggregate has no equivalent on
 * `NonConformityRepositoryPort`.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionComplianceStatisticsAdapter implements InspectionComplianceStatisticsPort
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

  /**
   * Constant OPEN_STATUSES.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  private const array OPEN_STATUSES = ['open', 'in_progress'];
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
  public function openNonConformitiesBySeverityByFacility(string $organizationId): array
  {
    $sql = <<<'SQL'
        SELECT
          COALESCE(i.facility_id, :unassignedKey) AS facility_key,
          nc.severity AS severity,
          COUNT(nc.id) AS severity_count
        FROM non_conformities nc
        INNER JOIN inspections i ON i.id = nc.inspection_id
        WHERE i.organization_id = :organizationId
          AND nc.status IN (:openStatuses)
        GROUP BY facility_key, nc.severity
      SQL;

    /** @var list<array{facility_key: string, severity: string, severity_count: int|string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery(
      $sql,
      [
        'unassignedKey' => self::UNASSIGNED_FACILITY_KEY,
        'organizationId' => $organizationId,
        'openStatuses' => self::OPEN_STATUSES,
      ],
      ['openStatuses' => ArrayParameterType::STRING],
    )->fetchAllAssociative();

    $counts = [];
    foreach ($rows as $row) {
      $facilityKey = $row['facility_key'];
      $counts[$facilityKey] ??= [
        NonConformitySeverity::LOW->value => 0,
        NonConformitySeverity::MEDIUM->value => 0,
        NonConformitySeverity::HIGH->value => 0,
        NonConformitySeverity::CRITICAL->value => 0,
      ];
      if (isset($counts[$facilityKey][$row['severity']])) {
        $counts[$facilityKey][$row['severity']] = (int) $row['severity_count'];
      }
    }

    /** @var array<string, array{low: int, medium: int, high: int, critical: int}> $counts */
    return $counts;
  }
  // #endregion
}
