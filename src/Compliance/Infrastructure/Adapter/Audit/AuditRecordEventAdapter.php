<?php

declare(strict_types=1);

namespace Compliance\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Compliance\Application\Contract\Event\{SafetyRegisterExportedEvent, SafetyRegisterSnapshotCreatedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Compliance-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
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
      'compliance.safety_register_exported_event' => 'onSafetyRegisterExported',
      'compliance.safety_register_snapshot_created_event' => 'onSafetyRegisterSnapshotCreated',
    ];
  }

  /**
   * Method onSafetyRegisterExported.
   *
   * Records every export of the regulatory "registre de sécurité" — a
   * traceable, plan-gated compliance action (who exported what scope, when,
   * under which plan).
   *
   * @since 1.1.0
   *
   * @param SafetyRegisterExportedEvent $event the domain event
   */
  public function onSafetyRegisterExported(SafetyRegisterExportedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'compliance.register_exported',
      organizationId: $event->organizationId,
      subjectType: null === $event->facilityId ? 'organization' : 'facility',
      subjectId: $event->facilityId ?? $event->organizationId,
      metadata: [
        'scope' => $event->scope,
        'plan_key' => $event->planKey,
        'generated_at' => $event->generatedAt,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onSafetyRegisterSnapshotCreated.
   *
   * Records every archived "registre de sécurité" snapshot — a dated,
   * plan-gated compliance archive whose SHA-256 content hash lands in the
   * tamper-evident ledger, so the stored PDF and the audit trail
   * corroborate each other (who archived what scope, when, under which
   * plan, and exactly which bytes).
   *
   * @since 1.6.0
   *
   * @param SafetyRegisterSnapshotCreatedEvent $event the domain event
   */
  public function onSafetyRegisterSnapshotCreated(SafetyRegisterSnapshotCreatedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'compliance.register_snapshot_created',
      organizationId: $event->organizationId,
      subjectType: 'safety_register_snapshot',
      subjectId: $event->snapshotId,
      metadata: [
        'scope' => $event->scope,
        'plan_key' => $event->planKey,
        'generated_at' => $event->generatedAt,
        'content_hash' => $event->contentHash,
        'size_bytes' => $event->sizeBytes,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }
  // #endregion
}
