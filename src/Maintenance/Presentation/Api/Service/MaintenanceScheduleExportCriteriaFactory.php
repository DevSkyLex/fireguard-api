<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Service;

use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Component\HttpFoundation\Request;

use function array_keys;
use function is_string;

/**
 * Service MaintenanceScheduleExportCriteriaFactory.
 *
 * Builds the filter shape {@see \Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort::countForExport()}/
 * {@see \Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort::listExportCandidates()}
 * expect, from the export controller's raw `Request` query string — only the
 * cheap, indexed filter subset (`facility`, `equipmentType`, `dueStatus`) of
 * the larger set {@see \Maintenance\Presentation\Api\Provider\MaintenanceScheduleProvider}
 * parses inline for the list endpoint. `dueBefore` is deliberately not
 * exposed here: it is not part of the schedule's indexed
 * `(organization_id, due_status, next_due_at)` filter and would force a
 * range scan across the whole export candidate set. Mirrors
 * `Intervention\...\InterventionExportCriteriaFactory`.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenanceScheduleExportCriteriaFactory
{
  // #region Methods
  /**
   * Method fromRequest.
   *
   * @since 1.0.0
   *
   * @param Request $request the incoming HTTP request
   *
   * @return array{facilityId: ?string, equipmentType: ?string, dueStatus: ?string} the parsed filters
   */
  public function fromRequest(Request $request): array
  {
    $query = $request->query;

    $facility = $query->get('facility');
    $equipmentType = $query->get('equipmentType');
    $dueStatus = $query->get('dueStatus');

    return [
      'facilityId' => is_string($facility) && '' !== $facility ? ResourceIriParser::id($facility, 'facilities') : null,
      'equipmentType' => is_string($equipmentType) && '' !== $equipmentType ? $equipmentType : null,
      'dueStatus' => is_string($dueStatus) && '' !== $dueStatus ? $dueStatus : null,
    ];
  }

  /**
   * Method appliedFilterKeys.
   *
   * Returns the names of the filters actually applied — used only to
   * populate the export's own audit-trail metadata, which must never carry
   * raw filter values.
   *
   * @since 1.0.0
   *
   * @param array{facilityId: ?string, equipmentType: ?string, dueStatus: ?string} $filters the resolved filters
   *
   * @return list<string> the applied filter field names
   */
  public function appliedFilterKeys(array $filters): array
  {
    $applied = [];
    foreach (array_keys($filters) as $key) {
      if (null !== $filters[$key]) {
        $applied[] = $key;
      }
    }

    return $applied;
  }
  // #endregion
}
