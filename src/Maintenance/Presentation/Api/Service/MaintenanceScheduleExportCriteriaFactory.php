<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Service;

use DateTimeImmutable;
use Exception;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function array_keys;
use function is_string;

/**
 * Service MaintenanceScheduleExportCriteriaFactory.
 *
 * Shares the list date parsing and applies every list filter to the CSV export.
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
   * @return array{facilityId: ?string, equipmentType: ?string, dueStatus: ?string, dueBefore: ?DateTimeImmutable} the parsed filters
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
      'dueBefore' => self::parseDueBefore($query->get('dueBefore')),
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
   * @param array{facilityId: ?string, equipmentType: ?string, dueStatus: ?string, dueBefore: ?DateTimeImmutable} $filters the resolved filters
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

  /**
   * Parse the same inclusive upper bound for lists and exports.
   */
  public static function parseDueBefore(mixed $value): ?DateTimeImmutable
  {
    if (!is_string($value) || '' === $value) {
      return null;
    }

    try {
      return new DateTimeImmutable($value);
    } catch (Exception $exception) {
      throw new BadRequestHttpException('Invalid "dueBefore" filter.', $exception);
    }
  }
  // #endregion
}
