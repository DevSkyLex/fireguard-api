<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Organization\Application\Contract\Event\Join\OrganizationJoinChangedEvent;
use Organization\Application\Contract\Event\Organization\{OrganizationArchivedEvent, OrganizationCreatedEvent, OrganizationOwnershipTransferredEvent, OrganizationRestoredEvent, OrganizationSuspendedEvent};
use Organization\Application\Contract\Event\OrganizationSettingsUpdatedEvent;
use Organization\Application\Contract\Event\Plan\OrganizationPlanChangedEvent;
use Organization\Application\Contract\Event\Security\{OrganizationLastAdminLockoutPreventedEvent, OrganizationPermissionGrantDeniedEvent};
use Organization\Application\Contract\Event\Team\{TeamCreatedEvent, TeamDeletedEvent, TeamMemberAddedEvent, TeamMemberRemovedEvent, TeamUpdatedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Organization-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
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
      'organization.organization_permission_grant_denied_event' => 'onOrganizationPermissionGrantDenied',
      'organization.organization_last_admin_lockout_prevented_event' => 'onOrganizationLastAdminLockoutPrevented',
      'organization.team_created_event' => 'onTeamCreated',
      'organization.team_updated_event' => 'onTeamUpdated',
      'organization.team_deleted_event' => 'onTeamDeleted',
      'organization.team_member_added_event' => 'onTeamMemberAdded',
      'organization.team_member_removed_event' => 'onTeamMemberRemoved',
      'organization.organization_join_changed_event' => 'onOrganizationJoinChanged',
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
   * Method onOrganizationPermissionGrantDenied.
   *
   * Records a refused privilege-escalation attempt.
   *
   * @since 1.0.0
   *
   * @param OrganizationPermissionGrantDeniedEvent $event the domain event
   */
  public function onOrganizationPermissionGrantDenied(OrganizationPermissionGrantDeniedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.permission_grant_denied',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'denied_permission' => $event->deniedPermission,
        'context' => $event->context,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onOrganizationLastAdminLockoutPrevented.
   *
   * Records a refused operation that would have removed
   * the last organization administrator.
   *
   * @since 1.0.0
   *
   * @param OrganizationLastAdminLockoutPreventedEvent $event the domain event
   */
  public function onOrganizationLastAdminLockoutPrevented(OrganizationLastAdminLockoutPreventedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.last_admin_lockout_prevented',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'attempted_action' => $event->attemptedAction,
        'member_id' => $event->memberId,
        'role_id' => $event->roleId,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onTeamCreated.
   *
   * Records the creation of an organization team.
   *
   * @since 1.0.0
   *
   * @param TeamCreatedEvent $event the domain event
   */
  public function onTeamCreated(TeamCreatedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.team_created',
      organizationId: $event->organizationId,
      subjectType: 'organization_team',
      subjectId: $event->teamId,
      metadata: [
        'name' => $event->name,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onTeamUpdated.
   *
   * Records the update of an organization team's name or description.
   *
   * @since 1.0.0
   *
   * @param TeamUpdatedEvent $event the domain event
   */
  public function onTeamUpdated(TeamUpdatedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.team_updated',
      organizationId: $event->organizationId,
      subjectType: 'organization_team',
      subjectId: $event->teamId,
      metadata: [
        'name' => $event->name,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onTeamDeleted.
   *
   * Records the deletion of an organization team.
   *
   * @since 1.0.0
   *
   * @param TeamDeletedEvent $event the domain event
   */
  public function onTeamDeleted(TeamDeletedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.team_deleted',
      organizationId: $event->organizationId,
      subjectType: 'organization_team',
      subjectId: $event->teamId,
      metadata: [],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onTeamMemberAdded.
   *
   * Records a member being added to an organization team.
   *
   * @since 1.0.0
   *
   * @param TeamMemberAddedEvent $event the domain event
   */
  public function onTeamMemberAdded(TeamMemberAddedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.team_member_added',
      organizationId: $event->organizationId,
      subjectType: 'organization_team_member',
      subjectId: $event->memberId,
      metadata: [
        'team_id' => $event->teamId,
        'role' => $event->role,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onTeamMemberRemoved.
   *
   * Records a member being removed from an organization team.
   *
   * @since 1.0.0
   *
   * @param TeamMemberRemovedEvent $event the domain event
   */
  public function onTeamMemberRemoved(TeamMemberRemovedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.team_member_removed',
      organizationId: $event->organizationId,
      subjectType: 'organization_team_member',
      subjectId: $event->memberId,
      metadata: [
        'team_id' => $event->teamId,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.join_' . $event->operation,
      organizationId: $event->organizationId,
      subjectType: 'organization_access',
      subjectId: $event->resourceId ?? $event->organizationId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.created',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'name' => $event->name,
        'owner_user_id' => $event->ownerUserId,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.archived',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.restored',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.suspended',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.settings_updated',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'changed_fields' => $event->changedFields,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
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
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.ownership_transferred',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'previous_owner_user_id' => $event->previousOwnerUserId,
        'new_owner_user_id' => $event->newOwnerUserId,
      ],
      occurredAt: $event->occurredAt,
    ));
  }
  // #endregion
}
