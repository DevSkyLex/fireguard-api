<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

use Organization\Domain\Event\Invitation\{OrganizationInvitationAcceptedEvent, OrganizationInvitationRevokedEvent, OrganizationInvitationSentEvent};
use Organization\Domain\Event\Member\{OrganizationMemberAddedEvent, OrganizationMemberRemovedEvent};
use Organization\Domain\Event\Role\{OrganizationRoleAssignedEvent, OrganizationRoleCreatedEvent, OrganizationRoleDeletedEvent, OrganizationRoleUnassignedEvent, OrganizationRoleUpdatedEvent};
use Organization\Domain\Event\Security\{OrganizationLastAdminLockoutPreventedEvent, OrganizationPermissionGrantDeniedEvent};
use Organization\Domain\Event\Team\{TeamCreatedEvent, TeamDeletedEvent, TeamMemberAddedEvent, TeamMemberRemovedEvent, TeamUpdatedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Records the organizationaccess event family. */
final readonly class OrganizationAccessAuditEventSubscriber extends AbstractAuditEventSubscriber implements EventSubscriberInterface
{
  /**
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'organization.organization_role_created_event' => 'onOrganizationRoleCreated',
      'organization.organization_role_updated_event' => 'onOrganizationRoleUpdated',
      'organization.organization_role_deleted_event' => 'onOrganizationRoleDeleted',
      'organization.organization_role_assigned_event' => 'onOrganizationRoleAssigned',
      'organization.organization_role_unassigned_event' => 'onOrganizationRoleUnassigned',
      'organization.organization_member_added_event' => 'onOrganizationMemberAdded',
      'organization.organization_member_removed_event' => 'onOrganizationMemberRemoved',
      'organization.organization_invitation_sent_event' => 'onOrganizationInvitationSent',
      'organization.organization_invitation_accepted_event' => 'onOrganizationInvitationAccepted',
      'organization.organization_invitation_revoked_event' => 'onOrganizationInvitationRevoked',
      'organization.organization_permission_grant_denied_event' => 'onOrganizationPermissionGrantDenied',
      'organization.organization_last_admin_lockout_prevented_event' => 'onOrganizationLastAdminLockoutPrevented',
      'organization.team_created_event' => 'onTeamCreated',
      'organization.team_updated_event' => 'onTeamUpdated',
      'organization.team_deleted_event' => 'onTeamDeleted',
      'organization.team_member_added_event' => 'onTeamMemberAdded',
      'organization.team_member_removed_event' => 'onTeamMemberRemoved',
    ];
  }

  /**
   * Method onOrganizationRoleCreated.
   *
   * Records the creation of an organization role.
   *
   * @since 1.0.0
   *
   * @param OrganizationRoleCreatedEvent $event the domain event
   */
  public function onOrganizationRoleCreated(OrganizationRoleCreatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.role_created',
      organizationId: $event->organizationId,
      subjectType: 'organization_role',
      subjectId: $event->roleId,
      metadata: [
        'role_name' => $event->roleName,
        'permissions' => $event->permissions,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onOrganizationRoleUpdated.
   *
   * Records the update of an organization role definition.
   *
   * @since 1.0.0
   *
   * @param OrganizationRoleUpdatedEvent $event the domain event
   */
  public function onOrganizationRoleUpdated(OrganizationRoleUpdatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.role_updated',
      organizationId: $event->organizationId,
      subjectType: 'organization_role',
      subjectId: $event->roleId,
      metadata: [
        'role_name' => $event->roleName,
        'permissions' => $event->permissions,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onOrganizationRoleDeleted.
   *
   * Records the deletion of an organization role.
   *
   * @since 1.0.0
   *
   * @param OrganizationRoleDeletedEvent $event the domain event
   */
  public function onOrganizationRoleDeleted(OrganizationRoleDeletedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.role_deleted',
      organizationId: $event->organizationId,
      subjectType: 'organization_role',
      subjectId: $event->roleId,
      metadata: [
        'role_name' => $event->roleName,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onOrganizationRoleAssigned.
   *
   * Records a role assignment to a member.
   *
   * @since 1.0.0
   *
   * @param OrganizationRoleAssignedEvent $event the domain event
   */
  public function onOrganizationRoleAssigned(OrganizationRoleAssignedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.role_assigned',
      organizationId: $event->organizationId,
      subjectType: 'organization_member',
      subjectId: $event->memberId,
      metadata: [
        'role_id' => $event->roleId,
        'role_name' => $event->roleName,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onOrganizationRoleUnassigned.
   *
   * Records a role removal from a member.
   *
   * @since 1.0.0
   *
   * @param OrganizationRoleUnassignedEvent $event the domain event
   */
  public function onOrganizationRoleUnassigned(OrganizationRoleUnassignedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.role_unassigned',
      organizationId: $event->organizationId,
      subjectType: 'organization_member',
      subjectId: $event->memberId,
      metadata: [
        'role_id' => $event->roleId,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onOrganizationMemberAdded.
   *
   * Records a member joining an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberAddedEvent $event the domain event
   */
  public function onOrganizationMemberAdded(OrganizationMemberAddedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.member_added',
      organizationId: $event->organizationId,
      subjectType: 'organization_member',
      subjectId: $event->memberId,
      metadata: [
        'user_id' => $event->userId,
        'role_ids' => $event->roleIds,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onOrganizationMemberRemoved.
   *
   * Records a member removal from an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRemovedEvent $event the domain event
   */
  public function onOrganizationMemberRemoved(OrganizationMemberRemovedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.member_removed',
      organizationId: $event->organizationId,
      subjectType: 'organization_member',
      subjectId: $event->memberId,
      metadata: [
        'user_id' => $event->userId,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onOrganizationInvitationSent.
   *
   * Records an organization invitation being sent or resent.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationSentEvent $event the domain event
   */
  public function onOrganizationInvitationSent(OrganizationInvitationSentEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.invitation_sent',
      organizationId: $event->organizationId,
      subjectType: 'organization_invitation',
      subjectId: $event->invitationId,
      metadata: [
        'invited_email' => $this->sanitizer->email($event->invitedEmail),
        'invited_email_hash' => $this->sanitizer->emailHash($event->invitedEmail),
        'resend' => $event->resend,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->invitedByUserId),
    );
  }

  /**
   * Method onOrganizationInvitationAccepted.
   *
   * Records an organization invitation acceptance.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationAcceptedEvent $event the domain event
   */
  public function onOrganizationInvitationAccepted(OrganizationInvitationAcceptedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.invitation_accepted',
      organizationId: $event->organizationId,
      subjectType: 'organization_invitation',
      subjectId: $event->invitationId,
      metadata: [
        'member_id' => $event->memberId,
        'role_ids' => $event->roleIds,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->userId, $event->userEmail),
    );
  }

  /**
   * Method onOrganizationInvitationRevoked.
   *
   * Records an organization invitation revocation.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationRevokedEvent $event the domain event
   */
  public function onOrganizationInvitationRevoked(OrganizationInvitationRevokedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'organization.invitation_revoked',
      organizationId: $event->organizationId,
      subjectType: 'organization_invitation',
      subjectId: $event->invitationId,
      metadata: [
        'reason' => $event->reason,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->revokedByUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'organization.permission_grant_denied',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'denied_permission' => $event->deniedPermission,
        'context' => $event->context,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
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
    );
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
    $this->recordOrganizationAudit(
      action: 'organization.team_created',
      organizationId: $event->organizationId,
      subjectType: 'organization_team',
      subjectId: $event->teamId,
      metadata: [
        'name' => $event->name,
      ],
      occurredAt: $event->occurredAt,
    );
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
    $this->recordOrganizationAudit(
      action: 'organization.team_updated',
      organizationId: $event->organizationId,
      subjectType: 'organization_team',
      subjectId: $event->teamId,
      metadata: [
        'name' => $event->name,
      ],
      occurredAt: $event->occurredAt,
    );
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
    $this->recordOrganizationAudit(
      action: 'organization.team_deleted',
      organizationId: $event->organizationId,
      subjectType: 'organization_team',
      subjectId: $event->teamId,
      metadata: [],
      occurredAt: $event->occurredAt,
    );
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
    $this->recordOrganizationAudit(
      action: 'organization.team_member_added',
      organizationId: $event->organizationId,
      subjectType: 'organization_team_member',
      subjectId: $event->memberId,
      metadata: [
        'team_id' => $event->teamId,
        'role' => $event->role,
      ],
      occurredAt: $event->occurredAt,
    );
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
    $this->recordOrganizationAudit(
      action: 'organization.team_member_removed',
      organizationId: $event->organizationId,
      subjectType: 'organization_team_member',
      subjectId: $event->memberId,
      metadata: [
        'team_id' => $event->teamId,
      ],
      occurredAt: $event->occurredAt,
    );
  }
}
