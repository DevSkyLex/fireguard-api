<?php

declare(strict_types=1);

namespace Approval\Infrastructure\Adapter\Audit;

use Approval\Application\Contract\Event\ApprovalWithdrawnEvent;
use Approval\Application\Contract\Event\Request\{ApprovalApprovedEvent, ApprovalExecutionFailedEvent, ApprovalExpiredEvent, ApprovalRejectedEvent, ApprovalRequestedEvent};
use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Approval-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
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
      'approval.approval_requested_event' => 'onApprovalRequested',
      'approval.approval_approved_event' => 'onApprovalApproved',
      'approval.approval_rejected_event' => 'onApprovalRejected',
      'approval.approval_withdrawn_event' => 'onApprovalWithdrawn',
      'approval.approval_expired_event' => 'onApprovalExpired',
      'approval.approval_execution_failed_event' => 'onApprovalExecutionFailed',
    ];
  }

  /**
   * Method onApprovalRequested.
   *
   * Records the creation of a pending four-eyes approval request (a
   * regulated action deferred by the organization's approval policy).
   *
   * @since 1.2.0
   *
   * @param ApprovalRequestedEvent $event the domain event
   */
  public function onApprovalRequested(ApprovalRequestedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'approval.requested',
      organizationId: $event->organizationId,
      subjectType: 'approval_request',
      subjectId: $event->requestId,
      metadata: [
        'action_type' => $event->actionType,
        'subject_id' => $event->subjectId,
        'requested_by_member_id' => $event->requestedByMemberId,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->requestedByUserId,
    ));
  }

  /**
   * Method onApprovalApproved.
   *
   * Records a four-eyes approval request being approved (and its deferred
   * action re-executed).
   *
   * @since 1.2.0
   *
   * @param ApprovalApprovedEvent $event the domain event
   */
  public function onApprovalApproved(ApprovalApprovedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'approval.approved',
      organizationId: $event->organizationId,
      subjectType: 'approval_request',
      subjectId: $event->requestId,
      metadata: [
        'action_type' => $event->actionType,
        'subject_id' => $event->subjectId,
        'decision_by_member_id' => $event->decisionByMemberId,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->decisionByUserId,
    ));
  }

  /**
   * Method onApprovalRejected.
   *
   * Records a four-eyes approval request being rejected; the deferred
   * action is never executed.
   *
   * @since 1.2.0
   *
   * @param ApprovalRejectedEvent $event the domain event
   */
  public function onApprovalRejected(ApprovalRejectedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'approval.rejected',
      organizationId: $event->organizationId,
      subjectType: 'approval_request',
      subjectId: $event->requestId,
      metadata: [
        'action_type' => $event->actionType,
        'subject_id' => $event->subjectId,
        'decision_by_member_id' => $event->decisionByMemberId,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->decisionByUserId,
    ));
  }

  /**
   * Method onApprovalWithdrawn.
   *
   * Records a four-eyes approval request being withdrawn; the deferred
   * action is never executed.
   *
   * @since 1.2.0
   *
   * @param ApprovalWithdrawnEvent $event the domain event
   */
  public function onApprovalWithdrawn(ApprovalWithdrawnEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'approval.withdrawn',
      organizationId: $event->organizationId,
      subjectType: 'approval_request',
      subjectId: $event->requestId,
      metadata: [
        'action_type' => $event->actionType,
        'subject_id' => $event->subjectId,
        'decision_by_member_id' => $event->decisionByMemberId,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->decisionByUserId,
    ));
  }

  /**
   * Method onApprovalExpired.
   *
   * Records a pending four-eyes approval request left undecided past its
   * expiry deadline (the scheduled sweep). The acting principal is always
   * the system.
   *
   * @since 1.2.0
   *
   * @param ApprovalExpiredEvent $event the domain event
   */
  public function onApprovalExpired(ApprovalExpiredEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'approval.expired',
      organizationId: $event->organizationId,
      subjectType: 'approval_request',
      subjectId: $event->requestId,
      metadata: [
        'action_type' => $event->actionType,
        'subject_id' => $event->subjectId,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onApprovalExecutionFailed.
   *
   * Records an approved request whose deferred action could no longer be
   * re-executed because its subject changed state since the request was
   * created; the request is transitioned to `cancelled` alongside this
   * event.
   *
   * @since 1.2.0
   *
   * @param ApprovalExecutionFailedEvent $event the domain event
   */
  public function onApprovalExecutionFailed(ApprovalExecutionFailedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'approval.execution_failed',
      organizationId: $event->organizationId,
      subjectType: 'approval_request',
      subjectId: $event->requestId,
      metadata: [
        'action_type' => $event->actionType,
        'subject_id' => $event->subjectId,
        'error' => $event->error,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->decisionByUserId,
    ));
  }
  // #endregion
}
