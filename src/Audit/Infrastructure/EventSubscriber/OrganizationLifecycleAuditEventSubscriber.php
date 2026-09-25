<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

use Organization\Application\Contract\Event\OrganizationSettingsUpdatedEvent;
use Organization\Domain\Event\Join\OrganizationJoinChangedEvent;
use Organization\Domain\Event\Organization\{OrganizationArchivedEvent, OrganizationCreatedEvent, OrganizationOwnershipTransferredEvent, OrganizationRestoredEvent, OrganizationSuspendedEvent};
use Organization\Domain\Event\Plan\OrganizationPlanChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Records the organizationlifecycle event family. */
final readonly class OrganizationLifecycleAuditEventSubscriber extends AbstractAuditEventSubscriber implements EventSubscriberInterface
{
  /**
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      OrganizationJoinChangedEvent::class => 'onOrganizationJoinChanged',
      'organization.organization_created_event' => 'onOrganizationCreated',
      'organization.organization_archived_event' => 'onOrganizationArchived',
      'organization.organization_restored_event' => 'onOrganizationRestored',
      'organization.organization_suspended_event' => 'onOrganizationSuspended',
      'organization.organization_settings_updated_event' => 'onOrganizationSettingsUpdated',
      'organization.organization_plan_changed_event' => 'onOrganizationPlanChanged',
      'organization.organization_ownership_transferred_event' => 'onOrganizationOwnershipTransferred',
    ];
  }

  /**
   * Method onOrganizationJoinChanged.
   *
   * Records committed access changes without DNS proofs or raw applicant emails.
   *
   * @since 1.0.0
   *
   * @param OrganizationJoinChangedEvent $event the committed domain event
   */
  public function onOrganizationJoinChanged(OrganizationJoinChangedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.join_' . $event->operation,
      organizationId: $event->organizationId,
      subjectType: 'organization_access',
      subjectId: $event->resourceId ?? $event->organizationId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onOrganizationCreated.
   *
   * Records the creation of an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationCreatedEvent $event the domain event
   */
  public function onOrganizationCreated(OrganizationCreatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.created',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'name' => $event->name,
        'owner_user_id' => $event->ownerUserId,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onOrganizationArchived.
   *
   * Records an organization archive (soft delete).
   *
   * @since 1.0.0
   *
   * @param OrganizationArchivedEvent $event the domain event
   */
  public function onOrganizationArchived(OrganizationArchivedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.archived',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onOrganizationRestored.
   *
   * Records an organization reactivation.
   *
   * @since 1.0.0
   *
   * @param OrganizationRestoredEvent $event the domain event
   */
  public function onOrganizationRestored(OrganizationRestoredEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.restored',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onOrganizationSuspended.
   *
   * Records an organization suspension.
   *
   * @since 1.0.0
   *
   * @param OrganizationSuspendedEvent $event the domain event
   */
  public function onOrganizationSuspended(OrganizationSuspendedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.suspended',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onOrganizationSettingsUpdated.
   *
   * Records an organization settings change.
   *
   * @since 1.0.0
   *
   * @param OrganizationSettingsUpdatedEvent $event the domain event
   */
  public function onOrganizationSettingsUpdated(OrganizationSettingsUpdatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.settings_updated',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'changed_fields' => $event->changedFields,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onOrganizationPlanChanged.
   *
   * Records an organization subscription plan change.
   *
   * @since 1.0.0
   *
   * @param OrganizationPlanChangedEvent $event the domain event
   */
  public function onOrganizationPlanChanged(OrganizationPlanChangedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.plan_changed',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'plan_id' => $event->planId,
        'previous_plan_id' => $event->previousPlanId,
        'over_quota_resources' => $event->overQuotaResources,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onOrganizationOwnershipTransferred.
   *
   * Records an organization ownership transfer.
   *
   * @since 1.0.0
   *
   * @param OrganizationOwnershipTransferredEvent $event the domain event
   */
  public function onOrganizationOwnershipTransferred(OrganizationOwnershipTransferredEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.ownership_transferred',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'previous_owner_user_id' => $event->previousOwnerUserId,
        'new_owner_user_id' => $event->newOwnerUserId,
      ],
      occurredAt: $event->occurredAt,
    );
  }
}
