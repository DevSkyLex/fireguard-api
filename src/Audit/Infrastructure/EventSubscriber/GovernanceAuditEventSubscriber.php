<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

use Approval\Application\Contract\Event\ApprovalWithdrawnEvent;
use Approval\Domain\Event\Request\{
  ApprovalApprovedEvent,
  ApprovalExecutionFailedEvent,
  ApprovalExpiredEvent,
  ApprovalRejectedEvent,
  ApprovalRequestedEvent
};
use Compliance\Domain\Event\SafetyRegisterSnapshotCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webhook\Domain\Event\Subscription\{WebhookSubscriptionCreatedEvent, WebhookSubscriptionDeletedEvent};

/** Records the governance event family. */
final readonly class GovernanceAuditEventSubscriber extends AbstractAuditEventSubscriber implements EventSubscriberInterface
{
  /**
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'compliance.safety_register_snapshot_created_event' => 'onSafetyRegisterSnapshotCreated',
      'webhook.webhook_subscription_created_event' => 'onWebhookSubscriptionCreated',
      'webhook.webhook_subscription_deleted_event' => 'onWebhookSubscriptionDeleted',
      'approval.approval_requested_event' => 'onApprovalRequested',
      'approval.approval_approved_event' => 'onApprovalApproved',
      'approval.approval_rejected_event' => 'onApprovalRejected',
      'approval.approval_withdrawn_event' => 'onApprovalWithdrawn',
      'approval.approval_expired_event' => 'onApprovalExpired',
      'approval.approval_execution_failed_event' => 'onApprovalExecutionFailed',
    ];
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
    $this->recordOrganizationAudit(
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
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onWebhookSubscriptionCreated.
   *
   * Records the creation of an outbound webhook subscription. Metadata
   * carries the target URL's host only — never the signing secret.
   *
   * @since 1.2.0
   *
   * @param WebhookSubscriptionCreatedEvent $event the domain event
   */
  public function onWebhookSubscriptionCreated(WebhookSubscriptionCreatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'webhook.subscription_created',
      organizationId: $event->organizationId,
      subjectType: 'webhook_subscription',
      subjectId: $event->subscriptionId,
      metadata: [
        'url_host' => $event->urlHost,
        'event_types' => $event->eventTypes,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onWebhookSubscriptionDeleted.
   *
   * Records the deletion of an outbound webhook subscription.
   *
   * @since 1.2.0
   *
   * @param WebhookSubscriptionDeletedEvent $event the domain event
   */
  public function onWebhookSubscriptionDeleted(WebhookSubscriptionDeletedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'webhook.subscription_deleted',
      organizationId: $event->organizationId,
      subjectType: 'webhook_subscription',
      subjectId: $event->subscriptionId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
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
      actor: new ExplicitAuditActor($event->requestedByUserId),
    );
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
    $this->recordOrganizationAudit(
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
      actor: new ExplicitAuditActor($event->decisionByUserId),
    );
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
    $this->recordOrganizationAudit(
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
      actor: new ExplicitAuditActor($event->decisionByUserId),
    );
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
    $this->recordOrganizationAudit(
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
      actor: new ExplicitAuditActor($event->decisionByUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'approval.expired',
      organizationId: $event->organizationId,
      subjectType: 'approval_request',
      subjectId: $event->requestId,
      metadata: [
        'action_type' => $event->actionType,
        'subject_id' => $event->subjectId,
      ],
      occurredAt: $event->occurredAt,
    );
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
    $this->recordOrganizationAudit(
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
      actor: new ExplicitAuditActor($event->decisionByUserId),
    );
  }
}
