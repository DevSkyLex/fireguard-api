<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Contract\Event\AuditEventsExportedEvent;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Audit-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
 *
 * @category Adapter
 */
final readonly class AuditRecordEventAdapter implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the published durable recording seam.
   *
   * @access public
   *
   * @param AuditRecordingPort $audit generic Audit write capability
   *
   * @return void
   */
  public function __construct(private AuditRecordingPort $audit)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Preserves the existing owner event names and audit action mapping.
   *
   * @access public
   *
   * @return array<string, string> owner event listeners
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'audit.audit_events_exported_event' => 'onAuditEventsExported',
    ];
  }

  /**
   * Method onAuditEventsExported.
   *
   * Records every audit-ledger CSV export (`audit.export`) — the ledger
   * auditing its own export action, the same as every other module's
   * significant action. Not organization-scoped like most other actions
   * here (an export may span tenants or match no `tenantId` filter at
   * all), so the recording request leaves organizationId unset.
   * Metadata never carries the raw filter
   * values applied to the export — only their field names — so this
   * ledger entry cannot itself become a place where a filtered
   * actorId/subjectId (potentially person-identifying) leaks out.
   *
   * @since 1.3.0
   *
   * @param AuditEventsExportedEvent $event the domain event
   */
  public function onAuditEventsExported(AuditEventsExportedEvent $event): void
  {

    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'audit.export_performed',
      actorType: null,
      actorId: $event->actorUserId,
      subjectType: 'audit_export',
      tenantId: $event->tenantId,
      metadata: [
        'format' => $event->format,
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ],
      occurredAt: $event->occurredAt,
    ));
  }
  // #endregion
}
