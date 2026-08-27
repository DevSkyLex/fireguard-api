<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Service;

use Inspection\Domain\ValueObject\{InspectionResult, InspectionStatus};
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function array_keys;
use function is_string;

/**
 * Service InspectionExportCriteriaFactory.
 *
 * Builds the `array<string, mixed>` filter shape {@see \Inspection\Application\Port\Outbound\InspectionRepositoryPort::countExportCandidates()}/
 * {@see \Inspection\Application\Port\Outbound\InspectionRepositoryPort::listExportCandidates()}
 * expect, from the export controller's raw `Request` query string — the
 * documented export filter subset (`equipmentId`, `facilityId`, `result`,
 * `status`, `performedAtFrom`, `performedAtTo`, `inspectorUserId`,
 * `checklistId`) of the larger set
 * {@see \Inspection\Presentation\Api\Provider\Inspection\ListInspectionsProvider}
 * parses inline for the list endpoint (minus `inspectorType` and free-text
 * `search`, deliberately excluded from the export: cheap equality/range
 * filters only). Mirrors `Intervention\...\InterventionExportCriteriaFactory`.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionExportCriteriaFactory
{
  // #region Methods
  /**
   * Method fromRequest.
   *
   * @since 1.0.0
   *
   * @param Request $request the incoming HTTP request
   *
   * @return array<string, mixed> the parsed filters
   */
  public function fromRequest(Request $request): array
  {
    $query = $request->query;
    $filters = [];

    foreach (['equipmentId', 'facilityId', 'performedAtFrom', 'performedAtTo', 'inspectorUserId', 'checklistId'] as $filter) {
      $value = $query->get($filter);
      if (is_string($value) && '' !== $value) {
        $filters[$filter] = $value;
      }
    }

    $result = $query->get('result');
    if (is_string($result) && '' !== $result) {
      if (null === InspectionResult::tryFrom($result)) {
        throw new BadRequestHttpException('The result filter must be one of: pass, fail, partial.');
      }
      $filters['result'] = $result;
    }

    $status = $query->get('status');
    if (is_string($status) && '' !== $status) {
      if (null === InspectionStatus::tryFrom($status)) {
        throw new BadRequestHttpException('The status filter must be one of: draft, submitted, closed, cancelled.');
      }
      $filters['status'] = $status;
    }

    return $filters;
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
   * @param array<string, mixed> $filters the resolved filters
   *
   * @return list<string> the applied filter field names
   */
  public function appliedFilterKeys(array $filters): array
  {
    return array_keys($filters);
  }
  // #endregion
}
