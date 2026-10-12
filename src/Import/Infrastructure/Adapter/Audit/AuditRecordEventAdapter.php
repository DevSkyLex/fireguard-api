<?php

declare(strict_types=1);

namespace Import\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Import\Application\Contract\Event\{ImportJobCompletedEvent, ImportJobFailedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Import-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
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
      'import.import_job_completed_event' => 'onImportJobCompleted',
      'import.import_job_failed_event' => 'onImportJobFailed',
    ];
  }

  /**
   * Method onImportJobCompleted.
   *
   * Records a bulk CSV import batch reaching completion — even a batch with
   * every row failed still completed (partial success). Metadata carries
   * counts only, never row payloads.
   *
   * @since 1.0.0
   *
   * @param ImportJobCompletedEvent $event the domain event
   */
  public function onImportJobCompleted(ImportJobCompletedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'import.job_completed',
      organizationId: $event->organizationId,
      subjectType: 'import_job',
      subjectId: $event->importJobId,
      metadata: [
        'kind' => $event->kind,
        'total_rows' => $event->totalRows,
        'successful_rows' => $event->successfulRows,
        'failed_rows' => $event->failedRows,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->createdBy,
    ));
  }

  /**
   * Method onImportJobFailed.
   *
   * Records a bulk CSV import batch failing catastrophically (unreadable or
   * malformed file) — individual row failures never reach this, they are
   * reported instead (see `onImportJobCompleted`).
   *
   * @since 1.0.0
   *
   * @param ImportJobFailedEvent $event the domain event
   */
  public function onImportJobFailed(ImportJobFailedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'import.job_failed',
      organizationId: $event->organizationId,
      subjectType: 'import_job',
      subjectId: $event->importJobId,
      metadata: [
        'kind' => $event->kind,
        'job_error' => $event->jobError,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->createdBy,
    ));
  }
  // #endregion
}
