<?php

declare(strict_types=1);

namespace Audit\Presentation\Api\Service;

use Audit\Application\Contract\AuditEventSearchCriteria;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;

use function is_string;

/**
 * Service AuditEventExportCriteriaFactory.
 *
 * Builds an {@see AuditEventSearchCriteria} from the export controller's raw
 * `Request` query string, reusing exactly the same filter set as the list
 * endpoint (`action`, `actorType`, `actorId`, `subjectType`, `subjectId`,
 * `clientId`, `tenantId`, `ipHash`, `from`, `to`) so an export always
 * matches what the caller is currently looking at.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuditEventExportCriteriaFactory
{
  // #region Methods
  /**
   * Method fromRequest.
   *
   * @since 1.0.0
   *
   * @param Request $request the incoming HTTP request
   *
   * @return AuditEventSearchCriteria the parsed search criteria
   */
  public function fromRequest(Request $request): AuditEventSearchCriteria
  {
    return new AuditEventSearchCriteria(
      action: $this->stringOrNull($request->query->get('action')),
      actorType: $this->stringOrNull($request->query->get('actorType')),
      actorId: $this->stringOrNull($request->query->get('actorId')),
      subjectType: $this->stringOrNull($request->query->get('subjectType')),
      subjectId: $this->stringOrNull($request->query->get('subjectId')),
      clientId: $this->stringOrNull($request->query->get('clientId')),
      tenantId: $this->stringOrNull($request->query->get('tenantId')),
      ipHash: $this->stringOrNull($request->query->get('ipHash')),
      from: $this->parseDate($request->query->get('from')),
      to: $this->parseDate($request->query->get('to')),
    );
  }

  /**
   * Method appliedFilterKeys.
   *
   * Returns the names of the filters that were actually applied — used
   * only to populate the export's own audit-trail metadata, which must
   * never carry raw filter values (some, like `actorId`/`subjectId`, can
   * be person-identifying), only their names.
   *
   * @since 1.0.0
   *
   * @param AuditEventSearchCriteria $criteria the resolved search criteria
   *
   * @return list<string> the applied filter field names
   */
  public function appliedFilterKeys(AuditEventSearchCriteria $criteria): array
  {
    $candidates = [
      'action' => $criteria->action,
      'actorType' => $criteria->actorType,
      'actorId' => $criteria->actorId,
      'subjectType' => $criteria->subjectType,
      'subjectId' => $criteria->subjectId,
      'clientId' => $criteria->clientId,
      'tenantId' => $criteria->tenantId,
      'ipHash' => $criteria->ipHash,
      'from' => $criteria->from,
      'to' => $criteria->to,
    ];

    $applied = [];
    foreach ($candidates as $key => $value) {
      if (null !== $value) {
        $applied[] = $key;
      }
    }

    return $applied;
  }

  /**
   * Method stringOrNull.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw query value
   *
   * @return string|null the sanitized string or null
   */
  private function stringOrNull(mixed $value): ?string
  {
    if (!is_string($value) || '' === $value) {
      return null;
    }

    return $value;
  }

  /**
   * Method parseDate.
   *
   * Parses a date string (ISO 8601 or parseable format), mirroring
   * `ListAuditEventsProvider::parseDate()`.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw query value
   *
   * @return DateTimeImmutable|null the parsed date or null
   */
  private function parseDate(mixed $value): ?DateTimeImmutable
  {
    if (!is_string($value) || '' === $value) {
      return null;
    }

    $parsed = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $value);
    if ($parsed instanceof DateTimeImmutable) {
      return $parsed;
    }

    return new DateTimeImmutable($value);
  }
  // #endregion
}
