<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventResult};
use Audit\Domain\Event\AuditEventsExportedEvent;
use Audit\Infrastructure\EventSubscriber\AuditEventSubscriber;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use Auth\Domain\Event\Session\{LoginFailedEvent, UserLoggedInEvent};
use Auth\Infrastructure\Security\User\SecurityUser;
use Calendar\Domain\Event\{CalendarEventCreatedEvent, CalendarEventDeletedEvent, CalendarEventUpdatedEvent};
use DateTimeImmutable;
use Intervention\Domain\Event\Workflow\InterventionStatusTransitionedEvent;
use Organization\Domain\Event\Invitation\OrganizationInvitationSentEvent;
use Organization\Domain\Event\Member\OrganizationMemberRemovedEvent;
use Organization\Domain\Event\Role\OrganizationRoleCreatedEvent;
use Organization\Domain\Event\Security\OrganizationLastAdminLockoutPreventedEvent;
use Organization\Domain\Event\Team\{TeamCreatedEvent, TeamMemberAddedEvent};
use Otp\Domain\Event\Totp\{TotpEnrollmentConfirmedEvent, TotpEnrollmentDisabledEvent};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Webhook\Domain\Event\Subscription\{WebhookSubscriptionCreatedEvent, WebhookSubscriptionDeletedEvent};

use function hash_hmac;

/**
 * Test AuditEventSubscriberTest.
 *
 * @category Event Subscriber Tests
 */
#[CoversClass(className: AuditEventSubscriber::class)]
final class AuditEventSubscriberTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testGetSubscribedEvents(): void
  {
    self::assertSame([
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
      'webhook.webhook_subscription_created_event' => 'onWebhookSubscriptionCreated',
      'webhook.webhook_subscription_deleted_event' => 'onWebhookSubscriptionDeleted',
      'approval.approval_requested_event' => 'onApprovalRequested',
      'approval.approval_approved_event' => 'onApprovalApproved',
      'approval.approval_rejected_event' => 'onApprovalRejected',
      'approval.approval_expired_event' => 'onApprovalExpired',
      'approval.approval_execution_failed_event' => 'onApprovalExecutionFailed',
      'audit.audit_events_exported_event' => 'onAuditEventsExported',
      'intervention.interventions_exported_event' => 'onInterventionsExported',
    ], AuditEventSubscriber::getSubscribedEvents());
  }

  #[Test]
  public function testOnUserLoggedInDispatchesAuditCommand(): void
  {
    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']);
    $request->headers->set('User-Agent', 'Mozilla/5.0');
    $request->headers->set('X-Request-Id', 'req-123');

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $sanitizer = new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (RecordAuditEventCommand $command): bool {
        return 'auth.login_success' === $command->action
          && 'user' === $command->actorType
          && 'user-123' === $command->actorId
          && 'user@example.com' === $command->actorEmail
          && hash_hmac('sha256', 'user@example.com', 'salt-for-tests') === $command->actorEmailHash
          && '203.0.113.10' === $command->ipAddress
          && hash_hmac('sha256', '203.0.113.10', 'salt-for-tests') === $command->ipHash
          && 'Mozilla/5.0' === $command->userAgent
          && ['request_id' => 'req-123'] === $command->metadata;
      }))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-123'));

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::never())
      ->method('error');

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: $sanitizer,
      requestStack: $requestStack,
      security: $this->securityWithUser(null),
      logger: $logger,
    );

    $subscriber->onUserLoggedIn(new UserLoggedInEvent(
      userId: 'user-123',
      email: 'user@example.com',
      ipAddress: null,
    ));
  }

  #[Test]
  public function testDispatchAuditEventLogsWhenDispatchFails(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('boom'));

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('error')
      ->with(
        'Failed to record audit event',
        self::callback(fn (array $context): bool => (
          ($context['error'] ?? null) === 'boom'
          && ($context['action'] ?? null) === 'auth.login_failed'
        )),
      );

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: false, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $logger,
    );

    $subscriber->onLoginFailed(new LoginFailedEvent(
      email: 'user@example.com',
      ipAddress: '127.0.0.1',
      reason: 'invalid_password',
    ));
  }

  #[Test]
  public function testOnTotpEnrollmentConfirmedDispatchesAuditCommand(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'otp.totp_enrolled' === $command->action
        && 'user' === $command->actorType
        && 'user-123' === $command->actorId))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-124'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: false, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onTotpEnrollmentConfirmed(new TotpEnrollmentConfirmedEvent(userId: 'user-123'));
  }

  #[Test]
  public function testOnTotpEnrollmentDisabledDispatchesAuditCommand(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'otp.totp_disabled' === $command->action
        && 'user' === $command->actorType
        && 'user-123' === $command->actorId))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-125'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: false, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onTotpEnrollmentDisabled(new TotpEnrollmentDisabledEvent(userId: 'user-123'));
  }

  #[Test]
  public function testOnOrganizationRoleCreatedResolvesActorFromSecurityToken(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'organization.role_created' === $command->action
        && 'user' === $command->actorType
        && 'admin-1' === $command->actorId
        && 'admin@example.com' === $command->actorEmail
        && 'organization_role' === $command->subjectType
        && 'role-1' === $command->subjectId
        && [
          'role_name' => 'Managers',
          'permissions' => ['organization.read'],
          'organization_id' => 'org-1',
        ] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-126'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(new SecurityUser(
        id: 'admin-1',
        email: 'admin@example.com',
        password: 'irrelevant',
      )),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onOrganizationRoleCreated(new OrganizationRoleCreatedEvent(
      organizationId: 'org-1',
      roleId: 'role-1',
      roleName: 'Managers',
      permissions: ['organization.read'],
    ));
  }

  #[Test]
  public function testOnOrganizationMemberRemovedFallsBackToSystemActor(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'organization.member_removed' === $command->action
        && 'system' === $command->actorType
        && null === $command->actorId
        && null === $command->actorEmail
        && 'organization_member' === $command->subjectType
        && 'member-1' === $command->subjectId
        && [
          'user_id' => 'user-9',
          'organization_id' => 'org-1',
        ] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-127'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onOrganizationMemberRemoved(new OrganizationMemberRemovedEvent(
      organizationId: 'org-1',
      memberId: 'member-1',
      userId: 'user-9',
    ));
  }

  #[Test]
  public function testOnOrganizationInvitationSentPrefersExplicitActorFromEvent(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'organization.invitation_sent' === $command->action
        && 'user' === $command->actorType
        && 'admin-7' === $command->actorId
        && null === $command->actorEmail
        && 'organization_invitation' === $command->subjectType
        && 'inv-1' === $command->subjectId
        && [
          'invited_email' => 'invitee@example.com',
          'invited_email_hash' => hash_hmac('sha256', 'invitee@example.com', 'salt-for-tests'),
          'resend' => true,
          'organization_id' => 'org-1',
        ] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-128'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(new SecurityUser(
        id: 'other-2',
        email: 'other@example.com',
        password: 'irrelevant',
      )),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onOrganizationInvitationSent(new OrganizationInvitationSentEvent(
      organizationId: 'org-1',
      invitationId: 'inv-1',
      invitedEmail: 'invitee@example.com',
      invitedByUserId: 'admin-7',
      resend: true,
    ));
  }

  #[Test]
  public function testOnOrganizationLastAdminLockoutPreventedRecordsRefusal(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'organization.last_admin_lockout_prevented' === $command->action
        && 'system' === $command->actorType
        && 'organization' === $command->subjectType
        && 'org-1' === $command->subjectId
        && [
          'attempted_action' => 'remove_member',
          'member_id' => 'member-1',
          'role_id' => null,
          'organization_id' => 'org-1',
        ] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-129'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onOrganizationLastAdminLockoutPrevented(new OrganizationLastAdminLockoutPreventedEvent(
      organizationId: 'org-1',
      attemptedAction: 'remove_member',
      memberId: 'member-1',
    ));
  }

  #[Test]
  public function testOnTeamCreatedRecordsTeamAudit(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'organization.team_created' === $command->action
        && 'organization_team' === $command->subjectType
        && 'team-1' === $command->subjectId
        && [
          'name' => 'Field crew A',
          'organization_id' => 'org-1',
        ] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-130'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onTeamCreated(new TeamCreatedEvent(
      organizationId: 'org-1',
      teamId: 'team-1',
      name: 'Field crew A',
    ));
  }

  #[Test]
  public function testOnTeamMemberAddedRecordsTeamMemberAudit(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'organization.team_member_added' === $command->action
        && 'organization_team_member' === $command->subjectType
        && 'member-1' === $command->subjectId
        && [
          'team_id' => 'team-1',
          'role' => 'lead',
          'organization_id' => 'org-1',
        ] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-131'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onTeamMemberAdded(new TeamMemberAddedEvent(
      organizationId: 'org-1',
      teamId: 'team-1',
      memberId: 'member-1',
      role: 'lead',
    ));
  }

  #[Test]
  public function testOnCalendarEventCreatedRecordsEventAudit(): void
  {
    $startsAt = new DateTimeImmutable('2026-08-01T09:00:00+02:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'calendar.event_created' === $command->action
        && 'user' === $command->actorType
        && 'user-1' === $command->actorId
        && 'calendar_event' === $command->subjectType
        && 'event-1' === $command->subjectId
        && [
          'title' => 'Fire drill',
          'starts_at' => $startsAt->format(DateTimeImmutable::ATOM),
          'organization_id' => 'org-1',
        ] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-201'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onCalendarEventCreated(new CalendarEventCreatedEvent(
      organizationId: 'org-1',
      eventId: 'event-1',
      title: 'Fire drill',
      startsAt: $startsAt,
      actorUserId: 'user-1',
    ));
  }

  #[Test]
  public function testOnCalendarEventUpdatedRecordsEventAudit(): void
  {
    $startsAt = new DateTimeImmutable('2026-08-01T10:00:00+02:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'calendar.event_updated' === $command->action
        && 'calendar_event' === $command->subjectType
        && 'event-1' === $command->subjectId
        && [
          'title' => 'Updated drill',
          'starts_at' => $startsAt->format(DateTimeImmutable::ATOM),
          'organization_id' => 'org-1',
        ] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-202'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onCalendarEventUpdated(new CalendarEventUpdatedEvent(
      organizationId: 'org-1',
      eventId: 'event-1',
      title: 'Updated drill',
      startsAt: $startsAt,
      actorUserId: 'user-1',
    ));
  }

  #[Test]
  public function testOnCalendarEventDeletedRecordsEventAudit(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'calendar.event_deleted' === $command->action
        && 'calendar_event' === $command->subjectType
        && 'event-1' === $command->subjectId
        && ['organization_id' => 'org-1'] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-203'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onCalendarEventDeleted(new CalendarEventDeletedEvent(
      organizationId: 'org-1',
      eventId: 'event-1',
      actorUserId: 'user-1',
    ));
  }

  #[Test]
  public function testOnWebhookSubscriptionCreatedRecordsSubscriptionAudit(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'webhook.subscription_created' === $command->action
        && 'webhook_subscription' === $command->subjectType
        && 'sub-1' === $command->subjectId
        && 'user' === $command->actorType
        && 'admin-1' === $command->actorId
        && [
          'url_host' => 'example.com',
          'event_types' => ['intervention.published'],
          'organization_id' => 'org-1',
        ] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-132'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onWebhookSubscriptionCreated(new WebhookSubscriptionCreatedEvent(
      organizationId: 'org-1',
      subscriptionId: 'sub-1',
      urlHost: 'example.com',
      eventTypes: ['intervention.published'],
      actorUserId: 'admin-1',
    ));
  }

  #[Test]
  public function testOnWebhookSubscriptionDeletedRecordsSubscriptionAudit(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'webhook.subscription_deleted' === $command->action
        && 'webhook_subscription' === $command->subjectType
        && 'sub-1' === $command->subjectId
        && 'user' === $command->actorType
        && 'admin-1' === $command->actorId
        && ['organization_id' => 'org-1'] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-133'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onWebhookSubscriptionDeleted(new WebhookSubscriptionDeletedEvent(
      organizationId: 'org-1',
      subscriptionId: 'sub-1',
      actorUserId: 'admin-1',
    ));
  }

  #[Test]
  public function testOnAuditEventsExportedRecordsExportAuditWithoutRawFilterValues(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'audit.export_performed' === $command->action
        && 'user' === $command->actorType
        && 'user-1' === $command->actorId
        && 'audit_export' === $command->subjectType
        && null === $command->subjectId
        && 'tenant-1' === $command->tenantId
        && [
          'format' => 'csv',
          'row_count' => 42,
          'filter_keys' => ['action', 'from', 'to'],
        ] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-134'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onAuditEventsExported(new AuditEventsExportedEvent(
      actorUserId: 'user-1',
      tenantId: 'tenant-1',
      format: 'csv',
      rowCount: 42,
      filterKeys: ['action', 'from', 'to'],
    ));
  }
  // #endregion

  #[Test]
  public function testOnInterventionStatusTransitionedRecordsTransitionAudit(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => 'intervention.status_transitioned' === $command->action
        && 'user' === $command->actorType
        && 'user-7' === $command->actorId
        && 'intervention' === $command->subjectType
        && 'intervention-1' === $command->subjectId
        && [
          'intervention_number' => 42,
          'from_status' => 'planned',
          'to_status' => 'in_progress',
          'organization_id' => 'org-1',
        ] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-200'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onInterventionStatusTransitioned(new InterventionStatusTransitionedEvent(
      organizationId: 'org-1',
      interventionId: 'intervention-1',
      interventionNumber: 42,
      actorUserId: 'user-7',
      fromStatus: 'planned',
      toStatus: 'in_progress',
    ));
  }

  #[Test]
  public function testOnInterventionStatusTransitionedIncludesReviewNoteForChangesRequested(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RecordAuditEventCommand $command): bool => [
        'intervention_number' => 42,
        'from_status' => 'submitted',
        'to_status' => 'changes_requested',
        'review_note' => 'Please redo the panel check.',
        'organization_id' => 'org-1',
      ] === $command->metadata))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-201'));

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $this->securityWithUser(null),
      logger: $this->createStub(LoggerInterface::class),
    );

    $subscriber->onInterventionStatusTransitioned(new InterventionStatusTransitionedEvent(
      organizationId: 'org-1',
      interventionId: 'intervention-1',
      interventionNumber: 42,
      actorUserId: 'user-7',
      fromStatus: 'submitted',
      toStatus: 'changes_requested',
      reviewNote: 'Please redo the panel check.',
    ));
  }

  // #region Helpers
  /**
   * Method securityWithUser.
   *
   * Builds a Security stub resolving the given user.
   *
   * @param SecurityUser|null $user the authenticated user, or null
   *
   * @return Security the security stub
   */
  private function securityWithUser(?SecurityUser $user): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    return $security;
  }
  // #endregion
}
