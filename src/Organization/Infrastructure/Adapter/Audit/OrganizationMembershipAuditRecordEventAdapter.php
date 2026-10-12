<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Organization\Application\Contract\Event\Invitation\{OrganizationInvitationAcceptedEvent, OrganizationInvitationRevokedEvent, OrganizationInvitationSentEvent};
use Organization\Application\Contract\Event\Member\{OrganizationMemberAddedEvent, OrganizationMemberRemovedEvent};
use Organization\Application\Contract\Event\Role\{OrganizationRoleAssignedEvent, OrganizationRoleCreatedEvent, OrganizationRoleDeletedEvent, OrganizationRoleUnassignedEvent, OrganizationRoleUpdatedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class OrganizationMembershipAuditRecordEventAdapter
 *
 * Projects Organization-owned role, membership and invitation event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
 *
 * @category Adapter
 */
final readonly class OrganizationMembershipAuditRecordEventAdapter implements EventSubscriberInterface
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
   * Preserves the owner membership event names without duplicate subscriptions.
   *
   * @access public
   *
   * @return array<string, string> owner membership event listeners
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.role_created',
      organizationId: $event->organizationId,
      subjectType: 'organization_role',
      subjectId: $event->roleId,
      metadata: [
        'role_name' => $event->roleName,
        'permissions' => $event->permissions,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.role_updated',
      organizationId: $event->organizationId,
      subjectType: 'organization_role',
      subjectId: $event->roleId,
      metadata: [
        'role_name' => $event->roleName,
        'permissions' => $event->permissions,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.role_deleted',
      organizationId: $event->organizationId,
      subjectType: 'organization_role',
      subjectId: $event->roleId,
      metadata: [
        'role_name' => $event->roleName,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.role_assigned',
      organizationId: $event->organizationId,
      subjectType: 'organization_member',
      subjectId: $event->memberId,
      metadata: [
        'role_id' => $event->roleId,
        'role_name' => $event->roleName,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.role_unassigned',
      organizationId: $event->organizationId,
      subjectType: 'organization_member',
      subjectId: $event->memberId,
      metadata: [
        'role_id' => $event->roleId,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.member_added',
      organizationId: $event->organizationId,
      subjectType: 'organization_member',
      subjectId: $event->memberId,
      metadata: [
        'user_id' => $event->userId,
        'role_ids' => $event->roleIds,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.member_removed',
      organizationId: $event->organizationId,
      subjectType: 'organization_member',
      subjectId: $event->memberId,
      metadata: [
        'user_id' => $event->userId,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.invitation_sent',
      organizationId: $event->organizationId,
      subjectType: 'organization_invitation',
      subjectId: $event->invitationId,
      metadata: [
        'resend' => $event->resend,
      ],
      emailMetadata: ['invited_email' => $event->invitedEmail],
      occurredAt: $event->occurredAt,
      actorId: $event->invitedByUserId,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.invitation_accepted',
      organizationId: $event->organizationId,
      subjectType: 'organization_invitation',
      subjectId: $event->invitationId,
      metadata: [
        'member_id' => $event->memberId,
        'role_ids' => $event->roleIds,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->userId,
      actorEmail: $event->userEmail,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'organization.invitation_revoked',
      organizationId: $event->organizationId,
      subjectType: 'organization_invitation',
      subjectId: $event->invitationId,
      metadata: [
        'reason' => $event->reason,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->revokedByUserId,
    ));
  }
  // #endregion
}
