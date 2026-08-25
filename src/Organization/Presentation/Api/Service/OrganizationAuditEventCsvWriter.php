<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Service;

use Organization\Application\UseCase\Query\Organization\ListOrganizationAuditEvents\OrganizationAuditEventResult;

use function fputcsv;
use function json_encode;

/**
 * Service OrganizationAuditEventCsvWriter.
 *
 * Writes the organization-scoped audit export.
 *
 * Deliberately NOT the platform `AuditEventCsvWriter`: that one writes the full
 * `AuditEventView`, including actor email, IP address, user agent and the
 * ledger chain hashes. The organization feed strips all of those on purpose,
 * and an export that reused the platform columns would hand back, in a file,
 * exactly what the read endpoint refuses to show on screen.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationAuditEventCsvWriter
{
  // #region Constants
  /**
   * The exported columns, in order.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  private const array COLUMNS = [
    'id',
    'action',
    'actor_type',
    'actor_id',
    'actor_is_organization_member',
    'subject_type',
    'subject_id',
    'metadata',
    'occurred_at',
    'recorded_at',
  ];
  // #endregion

  // #region Methods
  /**
   * Method write.
   *
   * Streams the header row and every entry to the open handle.
   *
   * @since 1.0.0
   *
   * @param iterable<OrganizationAuditEventResult> $rows the entries to write, in stream order
   * @param resource $handle an open, writable stream resource
   */
  public function write(iterable $rows, $handle): void
  {
    fputcsv($handle, self::COLUMNS, escape: '');

    foreach ($rows as $row) {
      fputcsv($handle, [
        $row->id,
        $row->action,
        $row->actorType,
        $row->actorId ?? '',
        $row->actorIsOrganizationMember ? 'true' : 'false',
        $row->subjectType ?? '',
        $row->subjectId ?? '',
        json_encode($row->metadata),
        $row->occurredAt,
        $row->recordedAt,
      ], escape: '');
    }
  }
  // #endregion
}
