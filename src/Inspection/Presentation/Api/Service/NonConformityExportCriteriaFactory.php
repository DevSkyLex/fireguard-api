<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Service;

use Inspection\Domain\ValueObject\{NonConformitySeverity, NonConformityStatus};
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function array_keys;
use function is_string;

/**
 * Service NonConformityExportCriteriaFactory.
 *
 * Builds the `array<string, mixed>` filter shape {@see \Inspection\Application\Port\Outbound\NonConformityRepositoryPort::countExportCandidates()}/
 * {@see \Inspection\Application\Port\Outbound\NonConformityRepositoryPort::listExportCandidates()}
 * expect, from the export controller's raw `Request` query string — the
 * same `severity`/`status` filter subset
 * {@see \Inspection\Presentation\Api\Provider\NonConformity\ListOrganizationNonConformitiesProvider}
 * parses inline for the list endpoint. Mirrors
 * `Intervention\...\InterventionExportCriteriaFactory` and
 * `Inspection\...\InspectionExportCriteriaFactory`.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NonConformityExportCriteriaFactory
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

    $severity = $query->get('severity');
    if (is_string($severity) && '' !== $severity) {
      if (null === NonConformitySeverity::tryFrom($severity)) {
        throw new BadRequestHttpException('The severity filter must be one of: low, medium, high, critical.');
      }
      $filters['severity'] = $severity;
    }

    $status = $query->get('status');
    if (is_string($status) && '' !== $status) {
      if (null === NonConformityStatus::tryFrom($status)) {
        throw new BadRequestHttpException('The status filter must be one of: open, in_progress, done, waived.');
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
