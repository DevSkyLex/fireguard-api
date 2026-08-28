<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

use Approval\Domain\Event\Request\{
  ApprovalApprovedEvent,
  ApprovalExecutionFailedEvent,
  ApprovalExpiredEvent,
  ApprovalRejectedEvent,
  ApprovalRequestedEvent
};
use Audit\Application\UseCase\Command\RecordAuditEvent\RecordAuditEventCommand;
use Audit\Domain\Event\AuditEventsExportedEvent;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use Auth\Domain\Event\Session\{LoginFailedEvent, UserLoggedInEvent, UserLoggedOutEvent};
use Auth\Domain\Event\Token\TokenIssuedEvent as AuthTokenIssuedEvent;
use Auth\Infrastructure\Security\User\SecurityUser;
use Automation\Domain\Event\Rule\{AutomationRuleExecutedEvent, AutomationRuleFailedEvent};
use Calendar\Domain\Event\{CalendarEventCreatedEvent, CalendarEventDeletedEvent, CalendarEventUpdatedEvent, CalendarFeedTokenCreatedEvent, CalendarFeedTokenRevokedEvent};
use Compliance\Domain\Event\{SafetyRegisterExportedEvent, SafetyRegisterSnapshotCreatedEvent};
use DateTimeImmutable;
use Equipment\Domain\Event\Equipment\{EquipmentCommissionedEvent, EquipmentDecommissionedEvent, EquipmentPutUnderMaintenanceEvent, EquipmentReturnedToStockEvent};
use Equipment\Domain\Event\Export\{EquipmentReportExportedEvent, EquipmentsExportedEvent};
use Facility\Domain\Event\Export\FacilitiesExportedEvent;
use Facility\Domain\Event\Facility\{FacilityArchivedEvent, FacilityCreatedEvent, FacilityMovedEvent, FacilityRestoredEvent, FacilitySubtreeDuplicatedEvent, FacilityUpdatedEvent};
use Import\Domain\Event\{ImportJobCompletedEvent, ImportJobFailedEvent};
use Inspection\Domain\Event\Export\{InspectionReportExportedEvent, InspectionsExportedEvent, NonConformitiesExportedEvent, NonConformitiesReportExportedEvent};
use Inspection\Domain\Event\Inspection\{InspectionCancelledEvent, InspectionClosedEvent, InspectionSubmittedEvent};
use Inspection\Domain\Event\NonConformity\{NonConformityRecordedEvent, NonConformityStatusChangedEvent};
use Intervention\Domain\Event\Export\InterventionsExportedEvent;
use Intervention\Domain\Event\InterventionReportExportedEvent;
use Intervention\Domain\Event\Publication\{InterventionPublicationFailedEvent, InterventionPublishedEvent};
use Intervention\Domain\Event\Recurrence\{
  InterventionRecurrenceCreatedEvent,
  InterventionRecurrenceDeletedEvent,
  InterventionRecurrenceMaterializedEvent,
  InterventionRecurrenceUpdatedEvent
};
use Intervention\Domain\Event\Workflow\InterventionStatusTransitionedEvent;
use Maintenance\Domain\Event\Campaign\MaintenanceCampaignGeneratedEvent;
use Maintenance\Domain\Event\Export\MaintenanceSchedulesExportedEvent;
use Maintenance\Domain\Event\Schedule\MaintenanceScheduleOverriddenEvent;
use Messaging\Domain\Event\Channel\{
  MessagingChannelCreatedEvent,
  MessagingChannelParentChangedEvent,
  MessagingChannelParticipantAddedEvent,
  MessagingChannelParticipantRemovedEvent,
  MessagingChannelTeamBindingChangedEvent
};
use Messaging\Domain\Event\Conversation\MessagingConversationArchivedEvent;
use Messaging\Domain\Event\Message\{MessagingMessageModeratedEvent, MessagingMessageUnpinModeratedEvent};
use OAuth\Domain\Event\Consent\ConsentGrantedEvent;
use OAuth\Domain\Event\Token\{TokenIssueFailedEvent, TokenIssuedEvent, TokenRefreshFailedEvent, TokenRefreshedEvent, TokenRevokedEvent};
use Organization\Domain\Event\Invitation\{OrganizationInvitationAcceptedEvent, OrganizationInvitationRevokedEvent, OrganizationInvitationSentEvent};
use Organization\Domain\Event\Member\{OrganizationMemberAddedEvent, OrganizationMemberRemovedEvent};
use Organization\Domain\Event\Organization\{OrganizationArchivedEvent, OrganizationCreatedEvent, OrganizationOwnershipTransferredEvent, OrganizationRestoredEvent, OrganizationSettingsUpdatedEvent, OrganizationSuspendedEvent};
use Organization\Domain\Event\Plan\OrganizationPlanChangedEvent;
use Organization\Domain\Event\Role\{OrganizationRoleAssignedEvent, OrganizationRoleCreatedEvent, OrganizationRoleDeletedEvent, OrganizationRoleUnassignedEvent, OrganizationRoleUpdatedEvent};
use Organization\Domain\Event\Security\{OrganizationLastAdminLockoutPreventedEvent, OrganizationPermissionGrantDeniedEvent};
use Organization\Domain\Event\Team\{TeamCreatedEvent, TeamDeletedEvent, TeamMemberAddedEvent, TeamMemberRemovedEvent, TeamUpdatedEvent};
use Otp\Domain\Event\Totp\{TotpEnrollmentConfirmedEvent, TotpEnrollmentDisabledEvent};
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;
use User\Domain\Event\{UserEmailChangeCancelledEvent, UserEmailChangeConfirmedEvent, UserEmailChangeRequestedEvent};
use Webhook\Domain\Event\Subscription\{WebhookSubscriptionCreatedEvent, WebhookSubscriptionDeletedEvent};

/**
 * Subscriber AuditEventSubscriber.
 *
 * Records security-sensitive events
 * into the audit ledger.
 *
 * @category Subscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuditEventSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AuditEventSubscriber class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param AuditPiiSanitizer $sanitizer the PII sanitizer
   * @param RequestStack $requestStack the request stack
   * @param Security $security the security helper resolving the current actor
   * @param LoggerInterface $logger the logger
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private AuditPiiSanitizer $sanitizer,
    private RequestStack $requestStack,
    private Security $security,
    #[Autowire(service: 'monolog.logger.security')]
    private LoggerInterface $logger,
  ) {
  }
  // #endregion

  /**
   * Method getSubscribedEvents.
   *
   * Returns the event map for the subscriber.
   *
   * @since 1.0.0
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'auth.user_logged_in_event' => 'onUserLoggedIn',
      'auth.login_failed_event' => 'onLoginFailed',
      'auth.user_logged_out_event' => 'onUserLoggedOut',
      'auth.token_issued_event' => 'onAuthTokenIssued',
      'oauth.token_issued_event' => 'onOAuthTokenIssued',
      'oauth.token_issue_failed_event' => 'onOAuthTokenIssueFailed',
      'oauth.token_refreshed_event' => 'onOAuthTokenRefreshed',
      'oauth.token_refresh_failed_event' => 'onOAuthTokenRefreshFailed',
      'oauth.token_revoked_event' => 'onOAuthTokenRevoked',
      'oauth.consent_granted_event' => 'onConsentGranted',
      'otp.totp_enrollment_confirmed_event' => 'onTotpEnrollmentConfirmed',
      'otp.totp_enrollment_disabled_event' => 'onTotpEnrollmentDisabled',
      'user.user_email_change_requested_event' => 'onUserEmailChangeRequested',
      'user.user_email_change_confirmed_event' => 'onUserEmailChangeConfirmed',
      'user.user_email_change_cancelled_event' => 'onUserEmailChangeCancelled',
      'organization.organization_created_event' => 'onOrganizationCreated',
      'organization.organization_archived_event' => 'onOrganizationArchived',
      'organization.organization_restored_event' => 'onOrganizationRestored',
      'organization.organization_suspended_event' => 'onOrganizationSuspended',
      'organization.organization_settings_updated_event' => 'onOrganizationSettingsUpdated',
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
      'organization.organization_plan_changed_event' => 'onOrganizationPlanChanged',
      'organization.organization_ownership_transferred_event' => 'onOrganizationOwnershipTransferred',
      'organization.organization_permission_grant_denied_event' => 'onOrganizationPermissionGrantDenied',
      'organization.organization_last_admin_lockout_prevented_event' => 'onOrganizationLastAdminLockoutPrevented',
      'organization.team_created_event' => 'onTeamCreated',
      'organization.team_updated_event' => 'onTeamUpdated',
      'organization.team_deleted_event' => 'onTeamDeleted',
      'organization.team_member_added_event' => 'onTeamMemberAdded',
      'organization.team_member_removed_event' => 'onTeamMemberRemoved',
      'inspection.inspection_submitted_event' => 'onInspectionSubmitted',
      'inspection.inspection_closed_event' => 'onInspectionClosed',
      'inspection.inspection_cancelled_event' => 'onInspectionCancelled',
      'inspection.non_conformity_recorded_event' => 'onNonConformityRecorded',
      'inspection.non_conformity_status_changed_event' => 'onNonConformityStatusChanged',
      'facility.facility_created_event' => 'onFacilityCreated',
      'facility.facility_archived_event' => 'onFacilityArchived',
      'facility.facility_restored_event' => 'onFacilityRestored',
      'facility.facility_moved_event' => 'onFacilityMoved',
      'facility.facility_updated_event' => 'onFacilityUpdated',
      'facility.facility_subtree_duplicated_event' => 'onFacilitySubtreeDuplicated',
      'equipment.equipment_commissioned_event' => 'onEquipmentCommissioned',
      'equipment.equipment_put_under_maintenance_event' => 'onEquipmentPutUnderMaintenance',
      'equipment.equipment_returned_to_stock_event' => 'onEquipmentReturnedToStock',
      'equipment.equipment_decommissioned_event' => 'onEquipmentDecommissioned',
      'intervention.intervention_published_event' => 'onInterventionPublished',
      'intervention.intervention_publication_failed_event' => 'onInterventionPublicationFailed',
      'intervention.intervention_status_transitioned_event' => 'onInterventionStatusTransitioned',
      'intervention.intervention_recurrence_created_event' => 'onInterventionRecurrenceCreated',
      'intervention.intervention_recurrence_updated_event' => 'onInterventionRecurrenceUpdated',
      'intervention.intervention_recurrence_deleted_event' => 'onInterventionRecurrenceDeleted',
      'intervention.intervention_recurrence_materialized_event' => 'onInterventionRecurrenceMaterialized',
      'intervention.intervention_report_exported_event' => 'onInterventionReportExported',
      'maintenance.maintenance_schedule_overridden_event' => 'onMaintenanceScheduleOverridden',
      'maintenance.maintenance_campaign_generated_event' => 'onMaintenanceCampaignGenerated',
      'automation.automation_rule_executed_event' => 'onAutomationRuleExecuted',
      'automation.automation_rule_failed_event' => 'onAutomationRuleFailed',
      'calendar.calendar_event_created_event' => 'onCalendarEventCreated',
      'calendar.calendar_event_updated_event' => 'onCalendarEventUpdated',
      'calendar.calendar_event_deleted_event' => 'onCalendarEventDeleted',
      'calendar.calendar_feed_token_created_event' => 'onCalendarFeedTokenCreated',
      'calendar.calendar_feed_token_revoked_event' => 'onCalendarFeedTokenRevoked',
      'messaging.messaging_conversation_archived_event' => 'onMessagingConversationArchived',
      'messaging.messaging_message_moderated_event' => 'onMessagingMessageModerated',
      'messaging.messaging_message_unpin_moderated_event' => 'onMessagingMessageUnpinModerated',
      'messaging.messaging_channel_created_event' => 'onMessagingChannelCreated',
      'messaging.messaging_channel_participant_added_event' => 'onMessagingChannelParticipantAdded',
      'messaging.messaging_channel_participant_removed_event' => 'onMessagingChannelParticipantRemoved',
      'messaging.messaging_channel_team_binding_changed_event' => 'onMessagingChannelTeamBindingChanged',
      'messaging.messaging_channel_parent_changed_event' => 'onMessagingChannelParentChanged',
      'import.import_job_completed_event' => 'onImportJobCompleted',
      'import.import_job_failed_event' => 'onImportJobFailed',
      'compliance.safety_register_exported_event' => 'onSafetyRegisterExported',
      'compliance.safety_register_snapshot_created_event' => 'onSafetyRegisterSnapshotCreated',
      'webhook.webhook_subscription_created_event' => 'onWebhookSubscriptionCreated',
      'webhook.webhook_subscription_deleted_event' => 'onWebhookSubscriptionDeleted',
      'approval.approval_requested_event' => 'onApprovalRequested',
      'approval.approval_approved_event' => 'onApprovalApproved',
      'approval.approval_rejected_event' => 'onApprovalRejected',
      'approval.approval_expired_event' => 'onApprovalExpired',
      'approval.approval_execution_failed_event' => 'onApprovalExecutionFailed',
      'audit.audit_events_exported_event' => 'onAuditEventsExported',
      'intervention.interventions_exported_event' => 'onInterventionsExported',
      'equipment.equipments_exported_event' => 'onEquipmentsExported',
      'facility.facilities_exported_event' => 'onFacilitiesExported',
      'inspection.inspections_exported_event' => 'onInspectionsExported',
      'inspection.non_conformities_exported_event' => 'onNonConformitiesExported',
      'maintenance.maintenance_schedules_exported_event' => 'onMaintenanceSchedulesExported',
      'inspection.inspection_report_exported_event' => 'onInspectionReportExported',
      'inspection.non_conformities_report_exported_event' => 'onNonConformitiesReportExported',
      'equipment.equipment_report_exported_event' => 'onEquipmentReportExported',
    ];
  }

  /**
   * Method onUserLoggedIn.
   *
   * Records a successful login audit event.
   *
   * @since 1.0.0
   *
   * @param UserLoggedInEvent $event the domain event
   */
  public function onUserLoggedIn(UserLoggedInEvent $event): void
  {
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'auth.login_success',
      actorType: 'user',
      actorId: $event->userId,
      actorEmail: $this->sanitizer->email($event->email),
      actorEmailHash: $this->sanitizer->emailHash($event->email),
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onLoginFailed.
   *
   * Records a failed login audit event.
   *
   * @since 1.0.0
   *
   * @param LoginFailedEvent $event the domain event
   */
  public function onLoginFailed(LoginFailedEvent $event): void
  {
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'auth.login_failed',
      actorType: 'anonymous',
      actorId: null,
      actorEmail: $this->sanitizer->email($event->email),
      actorEmailHash: $this->sanitizer->emailHash($event->email),
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'reason' => $event->reason,
      ]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onUserLoggedOut.
   *
   * Records a logout audit event.
   *
   * @since 1.0.0
   *
   * @param UserLoggedOutEvent $event the domain event
   */
  public function onUserLoggedOut(UserLoggedOutEvent $event): void
  {
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'auth.logout',
      actorType: $event->userId ? 'user' : 'anonymous',
      actorId: $event->userId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'refresh_token_revoked' => $event->refreshTokenRevoked,
        'access_token_revoked' => $event->accessTokenRevoked,
      ]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onAuthTokenIssued.
   *
   * Records a token issued event from the Auth module.
   *
   * @since 1.0.0
   *
   * @param AuthTokenIssuedEvent $event the domain event
   */
  public function onAuthTokenIssued(AuthTokenIssuedEvent $event): void
  {
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];
    $actorType = $event->userId ? 'user' : 'client';
    $actorId = $event->userId ?? $event->clientId;

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'auth.token_issued',
      actorType: $actorType,
      actorId: $actorId,
      subjectType: 'token',
      subjectId: $event->tokenId,
      clientId: $event->clientId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'grant_type' => $event->grantType,
        'scopes' => $event->scopes,
        'expires_in' => $event->expiresIn,
        'user_id' => $event->userId,
      ]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onOAuthTokenIssued.
   *
   * Records a token issued event from the OAuth module.
   *
   * @since 1.0.0
   *
   * @param TokenIssuedEvent $event the domain event
   */
  public function onOAuthTokenIssued(TokenIssuedEvent $event): void
  {
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'oauth.token_issued',
      actorType: 'client',
      actorId: $event->clientId,
      subjectType: 'token',
      subjectId: $event->tokenId,
      clientId: $event->clientId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'grant_type' => $event->grantType,
        'scopes' => $event->scopes,
        'expires_in' => $event->expiresIn,
        'user_id' => $event->userId,
      ]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onOAuthTokenIssueFailed.
   *
   * Records a token issuance failure.
   *
   * @since 1.0.0
   *
   * @param TokenIssueFailedEvent $event the domain event
   */
  public function onOAuthTokenIssueFailed(TokenIssueFailedEvent $event): void
  {
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'oauth.token_issue_failed',
      actorType: 'client',
      actorId: $event->clientId,
      clientId: $event->clientId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'grant_type' => $event->grantType,
        'reason' => $event->reason,
      ]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onOAuthTokenRefreshed.
   *
   * Records a successful token refresh.
   *
   * @since 1.0.0
   *
   * @param TokenRefreshedEvent $event the domain event
   */
  public function onOAuthTokenRefreshed(TokenRefreshedEvent $event): void
  {
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'oauth.token_refreshed',
      actorType: 'user',
      actorId: $event->userId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onOAuthTokenRefreshFailed.
   *
   * Records a failed token refresh.
   *
   * @since 1.0.0
   *
   * @param TokenRefreshFailedEvent $event the domain event
   */
  public function onOAuthTokenRefreshFailed(TokenRefreshFailedEvent $event): void
  {
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'oauth.token_refresh_failed',
      actorType: 'user',
      actorId: $event->userId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'reason' => $event->reason,
      ]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onOAuthTokenRevoked.
   *
   * Records a token revocation event.
   *
   * @since 1.0.0
   *
   * @param TokenRevokedEvent $event the domain event
   */
  public function onOAuthTokenRevoked(TokenRevokedEvent $event): void
  {
    $context = $this->requestContext();
    $ip = $event->ipAddress ?? $context['ip'];

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'oauth.token_revoked',
      actorType: $event->clientId ? 'client' : 'system',
      actorId: $event->clientId,
      subjectType: 'token',
      subjectId: $event->tokenId,
      clientId: $event->clientId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'token_type' => $event->tokenType,
        'reason' => $event->reason,
        'user_id' => $event->userId,
      ]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onConsentGranted.
   *
   * Records a consent grant/update event.
   *
   * @since 1.0.0
   *
   * @param ConsentGrantedEvent $event the domain event
   */
  public function onConsentGranted(ConsentGrantedEvent $event): void
  {
    $context = $this->requestContext();
    $ip = $context['ip'];
    $action = $event->isNew ? 'oauth.consent_granted' : 'oauth.consent_updated';

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: $action,
      actorType: 'user',
      actorId: $event->userId,
      subjectType: 'client',
      subjectId: $event->clientId,
      clientId: $event->clientId,
      ipAddress: $this->sanitizer->ip($ip),
      ipHash: $this->sanitizer->ipHash($ip),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'scopes' => $event->scopes,
        'is_new' => $event->isNew,
      ]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onTotpEnrollmentConfirmed.
   *
   * Records a TOTP enrollment activation.
   *
   * @since 1.0.0
   *
   * @param TotpEnrollmentConfirmedEvent $event the domain event
   */
  public function onTotpEnrollmentConfirmed(TotpEnrollmentConfirmedEvent $event): void
  {
    $context = $this->requestContext();

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'otp.totp_enrolled',
      actorType: 'user',
      actorId: $event->userId,
      ipAddress: $this->sanitizer->ip($context['ip']),
      ipHash: $this->sanitizer->ipHash($context['ip']),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onTotpEnrollmentDisabled.
   *
   * Records a TOTP enrollment being disabled.
   *
   * @since 1.0.0
   *
   * @param TotpEnrollmentDisabledEvent $event the domain event
   */
  public function onTotpEnrollmentDisabled(TotpEnrollmentDisabledEvent $event): void
  {
    $context = $this->requestContext();

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'otp.totp_disabled',
      actorType: 'user',
      actorId: $event->userId,
      ipAddress: $this->sanitizer->ip($context['ip']),
      ipHash: $this->sanitizer->ipHash($context['ip']),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onUserEmailChangeRequested.
   *
   * Records a sign-in email change request. Both addresses are PII:
   * they go through the sanitizer like every other email in the
   * ledger — sanitized display form plus deterministic hash, never
   * the raw value in the metadata.
   *
   * @since 1.0.0
   *
   * @param UserEmailChangeRequestedEvent $event the domain event
   */
  public function onUserEmailChangeRequested(UserEmailChangeRequestedEvent $event): void
  {
    $this->recordEmailChangeAudit('user.email_change_requested', $event->userId, $event->currentEmail, $event->newEmail, $event->occurredAt);
  }

  /**
   * Method onUserEmailChangeConfirmed.
   *
   * Records an applied sign-in email change (sessions revoked).
   *
   * @since 1.0.0
   *
   * @param UserEmailChangeConfirmedEvent $event the domain event
   */
  public function onUserEmailChangeConfirmed(UserEmailChangeConfirmedEvent $event): void
  {
    $this->recordEmailChangeAudit('user.email_change_confirmed', $event->userId, $event->currentEmail, $event->newEmail, $event->occurredAt);
  }

  /**
   * Method onUserEmailChangeCancelled.
   *
   * Records a cancelled sign-in email change request.
   *
   * @since 1.0.0
   *
   * @param UserEmailChangeCancelledEvent $event the domain event
   */
  public function onUserEmailChangeCancelled(UserEmailChangeCancelledEvent $event): void
  {
    $this->recordEmailChangeAudit('user.email_change_cancelled', $event->userId, $event->currentEmail, $event->newEmail, $event->occurredAt);
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
      actorUserId: $event->invitedByUserId,
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
      actorUserId: $event->userId,
      actorEmail: $event->userEmail,
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
      actorUserId: $event->revokedByUserId,
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
      actorUserId: $event->actorUserId,
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

  /**
   * Method onFacilityCreated.
   *
   * Records a facility creation.
   *
   * @since 1.0.0
   *
   * @param FacilityCreatedEvent $event the domain event
   */
  public function onFacilityCreated(FacilityCreatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.created',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onFacilityArchived.
   *
   * Records a facility archival.
   *
   * @since 1.0.0
   *
   * @param FacilityArchivedEvent $event the domain event
   */
  public function onFacilityArchived(FacilityArchivedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.archived',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onFacilityRestored.
   *
   * Records an archived facility being restored.
   *
   * @since 1.0.0
   *
   * @param FacilityRestoredEvent $event the domain event
   */
  public function onFacilityRestored(FacilityRestoredEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.restored',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onFacilityMoved.
   *
   * Records a facility move in the location hierarchy.
   *
   * @since 1.0.0
   *
   * @param FacilityMovedEvent $event the domain event
   */
  public function onFacilityMoved(FacilityMovedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.moved',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [
        'previous_parent_facility_id' => $event->previousParentFacilityId,
        'new_parent_facility_id' => $event->newParentFacilityId,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onFacilityUpdated.
   *
   * Records a facility descriptive-field update. `changed_fields` carries
   * only the field NAMES (never their values) — the ledger must not become
   * a second copy of potentially sensitive facility data (address,
   * metadata) nor grow noisy with every partial patch's payload.
   *
   * @since 1.0.0
   *
   * @param FacilityUpdatedEvent $event the domain event
   */
  public function onFacilityUpdated(FacilityUpdatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.updated',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [
        'changed_fields' => $event->changedFields,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onFacilitySubtreeDuplicated.
   *
   * Records a facility subtree duplication.
   *
   * @since 1.0.0
   *
   * @param FacilitySubtreeDuplicatedEvent $event the domain event
   */
  public function onFacilitySubtreeDuplicated(FacilitySubtreeDuplicatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.subtree_duplicated',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->sourceFacilityId,
      metadata: [
        'new_root_facility_id' => $event->newRootFacilityId,
        'node_count' => $event->nodeCount,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onEquipmentCommissioned.
   *
   * Records equipment entering service.
   *
   * @since 1.0.0
   *
   * @param EquipmentCommissionedEvent $event the domain event
   */
  public function onEquipmentCommissioned(EquipmentCommissionedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'equipment.commissioned',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'facility_id' => $event->facilityId,
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onEquipmentPutUnderMaintenance.
   *
   * Records equipment being placed under maintenance.
   *
   * @since 1.0.0
   *
   * @param EquipmentPutUnderMaintenanceEvent $event the domain event
   */
  public function onEquipmentPutUnderMaintenance(EquipmentPutUnderMaintenanceEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'equipment.under_maintenance',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'facility_id' => $event->facilityId,
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onEquipmentReturnedToStock.
   *
   * Records equipment being taken out of service.
   *
   * @since 1.0.0
   *
   * @param EquipmentReturnedToStockEvent $event the domain event
   */
  public function onEquipmentReturnedToStock(EquipmentReturnedToStockEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'equipment.returned_to_stock',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onEquipmentDecommissioned.
   *
   * Records a permanent equipment decommission.
   *
   * @since 1.0.0
   *
   * @param EquipmentDecommissionedEvent $event the domain event
   */
  public function onEquipmentDecommissioned(EquipmentDecommissionedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'equipment.decommissioned',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onInspectionSubmitted.
   *
   * Records an inspection submission.
   *
   * @since 1.0.0
   *
   * @param InspectionSubmittedEvent $event the domain event
   */
  public function onInspectionSubmitted(InspectionSubmittedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'inspection.submitted',
      organizationId: $event->organizationId,
      subjectType: 'inspection',
      subjectId: $event->inspectionId,
      metadata: [
        'equipment_id' => $event->equipmentId,
        'result' => $event->result,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onInspectionClosed.
   *
   * Records an inspection closure.
   *
   * @since 1.0.0
   *
   * @param InspectionClosedEvent $event the domain event
   */
  public function onInspectionClosed(InspectionClosedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'inspection.closed',
      organizationId: $event->organizationId,
      subjectType: 'inspection',
      subjectId: $event->inspectionId,
      metadata: [
        'equipment_id' => $event->equipmentId,
        'result' => $event->result,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onInspectionCancelled.
   *
   * Records an inspection logical annulment.
   *
   * @since 1.0.0
   *
   * @param InspectionCancelledEvent $event the domain event
   */
  public function onInspectionCancelled(InspectionCancelledEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'inspection.cancelled',
      organizationId: $event->organizationId,
      subjectType: 'inspection',
      subjectId: $event->inspectionId,
      metadata: [
        'equipment_id' => $event->equipmentId,
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onNonConformityRecorded.
   *
   * Records the discovery of a non-conformity.
   *
   * @since 1.0.0
   *
   * @param NonConformityRecordedEvent $event the domain event
   */
  public function onNonConformityRecorded(NonConformityRecordedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'inspection.non_conformity_recorded',
      organizationId: $event->organizationId,
      subjectType: 'non_conformity',
      subjectId: $event->nonConformityId,
      metadata: [
        'inspection_id' => $event->inspectionId,
        'severity' => $event->severity,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onNonConformityStatusChanged.
   *
   * Records a non-conformity status transition
   * (done/waived resolve the deficiency).
   *
   * @since 1.0.0
   *
   * @param NonConformityStatusChangedEvent $event the domain event
   */
  public function onNonConformityStatusChanged(NonConformityStatusChangedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'inspection.non_conformity_status_changed',
      organizationId: $event->organizationId,
      subjectType: 'non_conformity',
      subjectId: $event->nonConformityId,
      metadata: [
        'inspection_id' => $event->inspectionId,
        'previous_status' => $event->previousStatus,
        'status' => $event->status,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onInterventionPublished.
   *
   * Records a completed intervention publication (all draft
   * resources materialized, all approved changes applied).
   *
   * @since 1.0.0
   *
   * @param InterventionPublishedEvent $event the domain event
   */
  public function onInterventionPublished(InterventionPublishedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'intervention.published',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: [
        'publication_id' => $event->publicationId,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onInterventionPublicationFailed.
   *
   * Records a failed intervention publication attempt.
   *
   * @since 1.0.0
   *
   * @param InterventionPublicationFailedEvent $event the domain event
   */
  public function onInterventionPublicationFailed(InterventionPublicationFailedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'intervention.publication_failed',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: [
        'publication_id' => $event->publicationId,
        'reason' => $event->reason,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onInterventionStatusTransitioned.
   *
   * Records a successful intervention status transition — every explicit
   * transition applied through the workflow gateway, plus the work-item-driven
   * `planned -> in_progress` auto-start. `review_note` is present only when
   * the target status is `changes_requested`.
   *
   * @since 1.0.0
   *
   * @param InterventionStatusTransitionedEvent $event the domain event
   */
  public function onInterventionStatusTransitioned(InterventionStatusTransitionedEvent $event): void
  {
    $metadata = [
      'intervention_number' => $event->interventionNumber,
      'from_status' => $event->fromStatus,
      'to_status' => $event->toStatus,
    ];
    if (null !== $event->reviewNote) {
      $metadata['review_note'] = $event->reviewNote;
    }

    $this->recordOrganizationAudit(
      action: 'intervention.status_transitioned',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: $metadata,
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onInterventionRecurrenceCreated.
   *
   * Records the creation of a recurring intervention schedule.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceCreatedEvent $event the domain event
   */
  public function onInterventionRecurrenceCreated(InterventionRecurrenceCreatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'intervention.recurrence_created',
      organizationId: $event->organizationId,
      subjectType: 'intervention_recurrence',
      subjectId: $event->recurrenceId,
      metadata: [
        'template_id' => $event->templateId,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onInterventionRecurrenceUpdated.
   *
   * Records an update to a recurring intervention schedule (including the
   * `isActive` toggle).
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceUpdatedEvent $event the domain event
   */
  public function onInterventionRecurrenceUpdated(InterventionRecurrenceUpdatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'intervention.recurrence_updated',
      organizationId: $event->organizationId,
      subjectType: 'intervention_recurrence',
      subjectId: $event->recurrenceId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onInterventionRecurrenceDeleted.
   *
   * Records the deletion of a recurring intervention schedule.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceDeletedEvent $event the domain event
   */
  public function onInterventionRecurrenceDeleted(InterventionRecurrenceDeletedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'intervention.recurrence_deleted',
      organizationId: $event->organizationId,
      subjectType: 'intervention_recurrence',
      subjectId: $event->recurrenceId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onInterventionRecurrenceMaterialized.
   *
   * Records a recurring materializer attempt (success or failure) for a
   * recurrence's due occurrence. The acting principal is always the system.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceMaterializedEvent $event the domain event
   */
  public function onInterventionRecurrenceMaterialized(InterventionRecurrenceMaterializedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'intervention.recurrence_materialized',
      organizationId: $event->organizationId,
      subjectType: 'intervention_recurrence',
      subjectId: $event->recurrenceId,
      metadata: [
        'succeeded' => $event->succeeded,
        'intervention_id' => $event->interventionId,
        'error' => $event->error,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onInterventionReportExported.
   *
   * Records every export of an intervention's PDF report — mirrors
   * `onSafetyRegisterExported`'s "who pulled this document" traceability.
   *
   * @since 1.1.0
   *
   * @param InterventionReportExportedEvent $event the domain event
   */
  public function onInterventionReportExported(InterventionReportExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'intervention.report_exported',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onMaintenanceScheduleOverridden.
   *
   * Records a maintenance schedule interval override being set or cleared.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleOverriddenEvent $event the domain event
   */
  public function onMaintenanceScheduleOverridden(MaintenanceScheduleOverriddenEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'maintenance.schedule_overridden',
      organizationId: $event->organizationId,
      subjectType: 'maintenance_schedule',
      subjectId: $event->scheduleId,
      metadata: [
        'equipment_id' => $event->equipmentId,
        'interval_override' => $event->intervalOverride,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onMaintenanceCampaignGenerated.
   *
   * Records an inspection campaign generated from due/overdue maintenance
   * schedules.
   *
   * @since 1.0.0
   *
   * @param MaintenanceCampaignGeneratedEvent $event the domain event
   */
  public function onMaintenanceCampaignGenerated(MaintenanceCampaignGeneratedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'maintenance.campaign_generated',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: [
        'work_items_count' => $event->workItemsCount,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onAutomationRuleExecuted.
   *
   * Records a successful automation rule execution (e.g. a corrective
   * intervention draft auto-created from a critical non-conformity). The
   * acting principal is always the system.
   *
   * @since 1.0.0
   *
   * @param AutomationRuleExecutedEvent $event the domain event
   */
  public function onAutomationRuleExecuted(AutomationRuleExecutedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'automation.rule_executed',
      organizationId: $event->organizationId,
      subjectType: 'automation_rule',
      subjectId: $event->subjectId,
      metadata: [
        'rule_key' => $event->ruleKey,
        'intervention_id' => $event->interventionId,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onAutomationRuleFailed.
   *
   * Records a failed automation rule execution. The acting principal is
   * always the system.
   *
   * @since 1.0.0
   *
   * @param AutomationRuleFailedEvent $event the domain event
   */
  public function onAutomationRuleFailed(AutomationRuleFailedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'automation.rule_failed',
      organizationId: $event->organizationId,
      subjectType: 'automation_rule',
      subjectId: $event->subjectId,
      metadata: [
        'rule_key' => $event->ruleKey,
        'error' => $event->error,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onCalendarEventCreated.
   *
   * Records the creation of a standalone organization calendar event.
   * `title`/`starts_at` are kept in the raw ledger payload (full detail,
   * hash-covered) but withheld from the organization-audience projection —
   * see {@see \Audit\Application\Service\OrganizationAuditMetadataProjection} —
   * because an event title is operator-typed free text (unlike a curated
   * organization/team name) and can embed identifying detail.
   *
   * @since 1.4.0
   *
   * @param CalendarEventCreatedEvent $event the domain event
   */
  public function onCalendarEventCreated(CalendarEventCreatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'calendar.event_created',
      organizationId: $event->organizationId,
      subjectType: 'calendar_event',
      subjectId: $event->eventId,
      metadata: [
        'title' => $event->title,
        'starts_at' => $event->startsAt->format(DateTimeImmutable::ATOM),
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onCalendarEventUpdated.
   *
   * Records an update to a standalone organization calendar event. Mirrors
   * `onCalendarEventCreated`'s free-text withholding rationale.
   *
   * @since 1.4.0
   *
   * @param CalendarEventUpdatedEvent $event the domain event
   */
  public function onCalendarEventUpdated(CalendarEventUpdatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'calendar.event_updated',
      organizationId: $event->organizationId,
      subjectType: 'calendar_event',
      subjectId: $event->eventId,
      metadata: [
        'title' => $event->title,
        'starts_at' => $event->startsAt->format(DateTimeImmutable::ATOM),
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onCalendarEventDeleted.
   *
   * Records the deletion of a standalone organization calendar event.
   *
   * @since 1.4.0
   *
   * @param CalendarEventDeletedEvent $event the domain event
   */
  public function onCalendarEventDeleted(CalendarEventDeletedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'calendar.event_deleted',
      organizationId: $event->organizationId,
      subjectType: 'calendar_event',
      subjectId: $event->eventId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onCalendarFeedTokenCreated.
   *
   * Records the creation (or rotation) of a member iCal feed token.
   * Identifiers only — the metadata never carries the secret nor its hash:
   * the ledger must not hold anything that shortens a brute-force of the
   * feed URL.
   *
   * @since 1.5.0
   *
   * @param CalendarFeedTokenCreatedEvent $event the domain event
   */
  public function onCalendarFeedTokenCreated(CalendarFeedTokenCreatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'calendar.feed_token_created',
      organizationId: $event->organizationId,
      subjectType: 'calendar_feed_token',
      subjectId: $event->tokenId,
      metadata: [
        'rotated' => $event->rotated,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onCalendarFeedTokenRevoked.
   *
   * Records the revocation of a member iCal feed token, whether explicit
   * (DELETE) or implicit (rotation). Mirrors `onCalendarFeedTokenCreated`'s
   * no-secret rule.
   *
   * @since 1.5.0
   *
   * @param CalendarFeedTokenRevokedEvent $event the domain event
   */
  public function onCalendarFeedTokenRevoked(CalendarFeedTokenRevokedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'calendar.feed_token_revoked',
      organizationId: $event->organizationId,
      subjectType: 'calendar_feed_token',
      subjectId: $event->tokenId,
      metadata: [
        'reason' => $event->reason,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onMessagingConversationArchived.
   *
   * Records a conversation archival. Low-volume, unlike per-message
   * activity (which is deliberately NOT audited).
   *
   * @since 1.0.0
   *
   * @param MessagingConversationArchivedEvent $event the domain event
   */
  public function onMessagingConversationArchived(MessagingConversationArchivedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'messaging.conversation_archived',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'subject_type' => $event->subjectType,
        'subject_id' => $event->subjectId,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onMessagingMessageModerated.
   *
   * Records a manager deleting another member's message. A self-delete by
   * the message's own author is NOT audited (low-volume moderation-only
   * event).
   *
   * @since 1.0.0
   *
   * @param MessagingMessageModeratedEvent $event the domain event
   */
  public function onMessagingMessageModerated(MessagingMessageModeratedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'messaging.message_moderated',
      organizationId: $event->organizationId,
      subjectType: 'messaging_message',
      subjectId: $event->messageId,
      metadata: [
        'conversation_id' => $event->conversationId,
        'author_member_id' => $event->authorMemberId,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onMessagingMessageUnpinModerated.
   *
   * Records a manager unpinning a message pinned by a DIFFERENT member. A
   * member unpinning their own pin is NOT audited (low-volume
   * moderation-only event, mirrors `onMessagingMessageModerated`).
   *
   * @since 1.1.0
   *
   * @param MessagingMessageUnpinModeratedEvent $event the domain event
   */
  public function onMessagingMessageUnpinModerated(MessagingMessageUnpinModeratedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'messaging.message_unpin_moderated',
      organizationId: $event->organizationId,
      subjectType: 'messaging_message',
      subjectId: $event->messageId,
      metadata: [
        'conversation_id' => $event->conversationId,
        'pinned_by_member_id' => $event->pinnedByMemberId,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onMessagingChannelCreated.
   *
   * Records the creation of a team/participant channel. A governance action
   * (channel lifecycle), unlike per-message activity which is not audited.
   *
   * @since 1.1.0
   *
   * @param MessagingChannelCreatedEvent $event the domain event
   */
  public function onMessagingChannelCreated(MessagingChannelCreatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'messaging.channel_created',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'name' => $event->name,
        'created_by_member_id' => $event->createdByMemberId,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onMessagingChannelParticipantAdded.
   *
   * Records a member being added to a channel (the access trail — who could
   * see a channel, and when).
   *
   * @since 1.1.0
   *
   * @param MessagingChannelParticipantAddedEvent $event the domain event
   */
  public function onMessagingChannelParticipantAdded(MessagingChannelParticipantAddedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'messaging.channel_participant_added',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'member_id' => $event->memberId,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onMessagingChannelParticipantRemoved.
   *
   * Records a member being removed from a channel (revocation of access).
   *
   * @since 1.1.0
   *
   * @param MessagingChannelParticipantRemovedEvent $event the domain event
   */
  public function onMessagingChannelParticipantRemoved(MessagingChannelParticipantRemovedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'messaging.channel_participant_removed',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'member_id' => $event->memberId,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onMessagingChannelTeamBindingChanged.
   *
   * Records a channel being bound to or unbound from a team (a governance
   * action that changes how the channel's participant set is maintained).
   *
   * @since 1.1.0
   *
   * @param MessagingChannelTeamBindingChangedEvent $event the domain event
   */
  public function onMessagingChannelTeamBindingChanged(MessagingChannelTeamBindingChangedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'messaging.channel_team_binding_changed',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'team_id' => $event->teamId,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onMessagingChannelParentChanged.
   *
   * Records a channel being nested under another channel, moved to a
   * different parent, or detached (L2.6) — a governance action on the
   * hierarchy shape, mirroring the team-binding audit above.
   *
   * @since 1.3.0
   *
   * @param MessagingChannelParentChangedEvent $event the domain event
   */
  public function onMessagingChannelParentChanged(MessagingChannelParentChangedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'messaging.channel_parent_changed',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'parent_conversation_id' => $event->parentConversationId,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
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
    $this->recordOrganizationAudit(
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
      actorUserId: $event->createdBy,
    );
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
    $this->recordOrganizationAudit(
      action: 'import.job_failed',
      organizationId: $event->organizationId,
      subjectType: 'import_job',
      subjectId: $event->importJobId,
      metadata: [
        'kind' => $event->kind,
        'job_error' => $event->jobError,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->createdBy,
    );
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
    $this->recordOrganizationAudit(
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
      actorUserId: $event->actorUserId,
    );
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
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onInterventionsExported.
   *
   * Records every CSV export of an organization's interventions — the
   * intervention module auditing its own export action, mirroring
   * {@see self::onAuditEventsExported()} and
   * {@see self::onSafetyRegisterExported()}. Metadata carries only the
   * applied filter *names*, never their raw values.
   *
   * @since 1.5.0
   *
   * @param InterventionsExportedEvent $event the domain event
   */
  public function onInterventionsExported(InterventionsExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'intervention.list_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onEquipmentsExported.
   *
   * Records every CSV export of an organization's equipment park — the
   * equipment module auditing its own export action, mirroring
   * {@see self::onInterventionsExported()}. The export carries no filters,
   * so the metadata holds the row count only.
   *
   * @since 1.6.0
   *
   * @param EquipmentsExportedEvent $event the domain event
   */
  public function onEquipmentsExported(EquipmentsExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'equipment.list_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onFacilitiesExported.
   *
   * Records every CSV export of an organization's facilities — the facility
   * module auditing its own export action, mirroring
   * {@see self::onInterventionsExported()}. Metadata carries only the
   * applied filter *names*, never their raw values.
   *
   * @since 1.6.0
   *
   * @param FacilitiesExportedEvent $event the domain event
   */
  public function onFacilitiesExported(FacilitiesExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.list_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onInspectionsExported.
   *
   * Records every CSV export of an organization's inspections — the
   * inspection module auditing its own export action, mirroring
   * {@see self::onInterventionsExported()}. Metadata carries only the
   * applied filter *names*, never their raw values.
   *
   * @since 1.6.0
   *
   * @param InspectionsExportedEvent $event the domain event
   */
  public function onInspectionsExported(InspectionsExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'inspection.list_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onNonConformitiesExported.
   *
   * Records every CSV export of an organization's non-conformities — the
   * inspection module auditing its own export action, mirroring
   * {@see self::onInterventionsExported()}. Metadata carries only the
   * applied filter *names*, never their raw values.
   *
   * @since 1.6.0
   *
   * @param NonConformitiesExportedEvent $event the domain event
   */
  public function onNonConformitiesExported(NonConformitiesExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'inspection.non_conformities_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onMaintenanceSchedulesExported.
   *
   * Records every CSV export of an organization's maintenance schedules —
   * the maintenance module auditing its own export action, mirroring
   * {@see self::onInterventionsExported()}. Metadata carries only the
   * applied filter *names*, never their raw values.
   *
   * @since 1.6.0
   *
   * @param MaintenanceSchedulesExportedEvent $event the domain event
   */
  public function onMaintenanceSchedulesExported(MaintenanceSchedulesExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'maintenance.schedules_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onInspectionReportExported.
   *
   * Records every export of an inspection's PDF report — a plan-gated
   * document pull, mirroring {@see self::onSafetyRegisterExported()}'s
   * "who pulled this document, under which plan" traceability.
   *
   * @since 1.7.0
   *
   * @param InspectionReportExportedEvent $event the domain event
   */
  public function onInspectionReportExported(InspectionReportExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'inspection.report_exported',
      organizationId: $event->organizationId,
      subjectType: 'inspection',
      subjectId: $event->inspectionId,
      metadata: [
        'plan_key' => $event->planKey,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onNonConformitiesReportExported.
   *
   * Records every export of an organization's non-conformities PDF report —
   * a plan-gated document pull, mirroring
   * {@see self::onNonConformitiesExported()} for the filter discipline
   * (names only, never values) and
   * {@see self::onSafetyRegisterExported()} for the plan traceability.
   *
   * @since 1.7.0
   *
   * @param NonConformitiesReportExportedEvent $event the domain event
   */
  public function onNonConformitiesReportExported(NonConformitiesReportExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'inspection.non_conformities_report_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
        'plan_key' => $event->planKey,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
    );
  }

  /**
   * Method onEquipmentReportExported.
   *
   * Records every export of an equipment's PDF sheet — a plan-gated
   * document pull, mirroring {@see self::onSafetyRegisterExported()}'s
   * "who pulled this document, under which plan" traceability.
   *
   * @since 1.7.0
   *
   * @param EquipmentReportExportedEvent $event the domain event
   */
  public function onEquipmentReportExported(EquipmentReportExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'equipment.report_exported',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'plan_key' => $event->planKey,
      ],
      occurredAt: $event->occurredAt,
      actorUserId: $event->actorUserId,
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
      actorUserId: $event->actorUserId,
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
      actorUserId: $event->actorUserId,
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
      actorUserId: $event->requestedByUserId,
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
      actorUserId: $event->decisionByUserId,
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
      actorUserId: $event->decisionByUserId,
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
      actorUserId: $event->decisionByUserId,
    );
  }

  /**
   * Method onAuditEventsExported.
   *
   * Records every audit-ledger CSV export (`audit.export`) — the ledger
   * auditing its own export action, the same as every other module's
   * significant action. Not organization-scoped like most other actions
   * here (an export may span tenants or match no `tenantId` filter at
   * all), so this dispatches directly rather than through
   * `recordOrganizationAudit()`. Metadata never carries the raw filter
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
    $context = $this->requestContext();
    $actor = $this->currentActor($event->actorUserId);

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'audit.export_performed',
      actorType: $actor['type'],
      actorId: $actor['id'],
      actorEmail: $this->sanitizer->email($actor['email']),
      actorEmailHash: $this->sanitizer->emailHash($actor['email']),
      subjectType: 'audit_export',
      tenantId: $event->tenantId,
      ipAddress: $this->sanitizer->ip($context['ip']),
      ipHash: $this->sanitizer->ipHash($context['ip']),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'format' => $event->format,
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ]),
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method recordEmailChangeAudit.
   *
   * Shared shape of the three email-change ledger entries.
   *
   * @since 1.0.0
   *
   * @param string $action the audit action
   * @param string $userId the acting user
   * @param string $currentEmail the current (old) address
   * @param string $newEmail the requested/applied new address
   * @param DateTimeImmutable $occurredAt when the event occurred
   */
  private function recordEmailChangeAudit(
    string $action,
    string $userId,
    string $currentEmail,
    string $newEmail,
    DateTimeImmutable $occurredAt,
  ): void {
    $context = $this->requestContext();

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: $action,
      actorType: 'user',
      actorId: $userId,
      actorEmail: $this->sanitizer->email($currentEmail),
      actorEmailHash: $this->sanitizer->emailHash($currentEmail),
      subjectType: 'user',
      subjectId: $userId,
      ipAddress: $this->sanitizer->ip($context['ip']),
      ipHash: $this->sanitizer->ipHash($context['ip']),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'current_email' => $this->sanitizer->email($currentEmail),
        'current_email_hash' => $this->sanitizer->emailHash($currentEmail),
        'new_email' => $this->sanitizer->email($newEmail),
        'new_email_hash' => $this->sanitizer->emailHash($newEmail),
      ]),
      occurredAt: $occurredAt,
    ));
  }

  /**
   * Method recordOrganizationAudit.
   *
   * Builds and dispatches an organization-scoped audit event.
   * The organization ID is always appended to the metadata (the
   * hash-covered source of truth) and mirrored into the dedicated
   * organizationId column so every organization action can be
   * filtered uniformly on an indexed column.
   *
   * @since 1.0.0
   *
   * @param string $action the audit action name
   * @param string $organizationId the organization ID
   * @param string $subjectType the audited subject type
   * @param string $subjectId the audited subject ID
   * @param array<string, mixed> $metadata the action metadata
   * @param DateTimeImmutable $occurredAt when the action happened
   * @param string|null $actorUserId explicit actor carried by the domain event
   * @param string|null $actorEmail explicit actor email carried by the domain event
   */
  private function recordOrganizationAudit(
    string $action,
    string $organizationId,
    string $subjectType,
    string $subjectId,
    array $metadata,
    DateTimeImmutable $occurredAt,
    ?string $actorUserId = null,
    ?string $actorEmail = null,
  ): void {
    $context = $this->requestContext();
    $actor = $this->currentActor($actorUserId, $actorEmail);
    $metadata['organization_id'] = $organizationId;

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: $action,
      actorType: $actor['type'],
      actorId: $actor['id'],
      actorEmail: $this->sanitizer->email($actor['email']),
      actorEmailHash: $this->sanitizer->emailHash($actor['email']),
      subjectType: $subjectType,
      subjectId: $subjectId,
      organizationId: $organizationId,
      ipAddress: $this->sanitizer->ip($context['ip']),
      ipHash: $this->sanitizer->ipHash($context['ip']),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta($metadata),
      occurredAt: $occurredAt,
    ));
  }

  /**
   * Method currentActor.
   *
   * Resolves the acting principal: the explicit user ID when the
   * domain event carries one, otherwise the authenticated security
   * user, falling back to system (CLI and async paths).
   *
   * @since 1.0.0
   *
   * @param string|null $explicitUserId actor user ID carried by the domain event
   * @param string|null $explicitEmail actor email carried by the domain event
   *
   * @return array{type: string, id: ?string, email: ?string} the resolved actor
   */
  private function currentActor(?string $explicitUserId = null, ?string $explicitEmail = null): array
  {
    $user = $this->security->getUser();
    $tokenId = $user instanceof SecurityUser ? $user->getId() : null;
    $tokenEmail = $user instanceof SecurityUser ? $user->getUserIdentifier() : null;
    $actorId = $explicitUserId ?? $tokenId;

    if (null === $actorId) {
      return ['type' => 'system', 'id' => null, 'email' => null];
    }

    return [
      'type' => 'user',
      'id' => $actorId,
      'email' => $explicitEmail ?? ($actorId === $tokenId ? $tokenEmail : null),
    ];
  }

  /**
   * Method dispatchAuditEvent.
   *
   * Dispatches a RecordAuditEventCommand and swallows errors.
   *
   * @since 1.0.0
   *
   * @param RecordAuditEventCommand $command the command to dispatch
   */
  private function dispatchAuditEvent(RecordAuditEventCommand $command): void
  {
    try {
      $this->commandBus->dispatch(command: $command);
    } catch (Throwable $exception) {
      $this->logger->error('Failed to record audit event', [
        'error' => $exception->getMessage(),
        'action' => $command->action,
      ]);
    }
  }

  /**
   * Method requestContext.
   *
   * Extracts request context (ip, user agent, request id).
   *
   * @since 1.0.0
   *
   * @return array{ip: ?string, user_agent: ?string, request_id: ?string}
   */
  private function requestContext(): array
  {
    $request = $this->requestStack->getCurrentRequest();

    return [
      'ip' => $request?->getClientIp(),
      'user_agent' => $request?->headers->get('User-Agent'),
      'request_id' => $request?->headers->get('X-Request-Id')
        ?? $request?->headers->get('X-Correlation-Id'),
    ];
  }

  /**
   * Method withRequestMeta.
   *
   * Appends request metadata to the event payload.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $metadata the base metadata
   *
   * @return array<string, mixed> the enriched metadata
   */
  private function withRequestMeta(array $metadata): array
  {
    $context = $this->requestContext();
    if (null !== $context['request_id']) {
      $metadata['request_id'] = $context['request_id'];
    }

    return $metadata;
  }
}
