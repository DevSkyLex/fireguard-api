<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Service;

use Facility\Domain\ValueObject\{FacilityStatus, FacilityType};
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function array_keys;
use function is_string;

/**
 * Service FacilityExportCriteriaFactory.
 *
 * Builds the `array<string, mixed>` filter shape
 * {@see \Facility\Application\UseCase\Query\ExportFacilities\ExportFacilitiesHandler}
 * expects, from the export controller's raw `Request` query string — the
 * same filter subset {@see \Facility\Presentation\Api\Provider\Facility\ListFacilitiesProvider}
 * parses inline for the list endpoint, so an export always matches what the
 * caller is currently filtering on. Mirrors
 * `Intervention\...\InterventionExportCriteriaFactory`.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityExportCriteriaFactory
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

    $filters['includeArchived'] = $query->getBoolean('includeArchived', false);

    $type = $query->get('type');
    if (is_string($type) && '' !== $type) {
      if (null === FacilityType::tryFrom($type)) {
        throw new BadRequestHttpException('The type filter must be one of: site, building, floor, zone, area.');
      }
      $filters['type'] = $type;
    }

    $status = $query->get('status');
    if (is_string($status) && '' !== $status) {
      if (null === FacilityStatus::tryFrom($status)) {
        throw new BadRequestHttpException('The status filter must be one of: active, archived.');
      }
      $filters['status'] = $status;
    }

    $parentFacilityId = $query->get('parentFacilityId');
    if (is_string($parentFacilityId) && '' !== $parentFacilityId) {
      $filters['parentFacilityId'] = $parentFacilityId;
    }

    $rootsOnly = $query->getBoolean('rootsOnly', false);
    if ($rootsOnly) {
      $filters['rootsOnly'] = true;
    }

    $code = $query->get('code');
    if (is_string($code) && '' !== $code) {
      $filters['code'] = $code;
    }

    $search = $query->get('search');
    if (is_string($search) && '' !== $search) {
      $filters['search'] = $search;
    }

    if ($query->has('hasCoordinates')) {
      $filters['hasCoordinates'] = $query->getBoolean('hasCoordinates');
    }

    return $filters;
  }

  /**
   * Method appliedFilterKeys.
   *
   * Returns the names of the filters actually applied — used only to
   * populate the export's own audit-trail metadata, which must never carry
   * raw filter values. `includeArchived` is excluded: it is always present
   * with a default, never a caller-applied narrowing filter.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $filters the resolved filters
   *
   * @return list<string> the applied filter field names
   */
  public function appliedFilterKeys(array $filters): array
  {
    unset($filters['includeArchived']);

    return array_keys($filters);
  }
  // #endregion
}
