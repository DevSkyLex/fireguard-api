<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventResult};
use Audit\Infrastructure\EventSubscriber\AuditEventSubscriber;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use Organization\Domain\Event\Invitation\{OrganizationInvitationAcceptedEvent, OrganizationInvitationRevokedEvent, OrganizationInvitationSentEvent};
use Organization\Domain\Event\Member\{OrganizationMemberAddedEvent, OrganizationMemberRemovedEvent};
use Organization\Domain\Event\Organization\{OrganizationArchivedEvent, OrganizationCreatedEvent, OrganizationRestoredEvent, OrganizationSettingsUpdatedEvent, OrganizationSuspendedEvent};
use Organization\Domain\Event\Plan\OrganizationPlanChangedEvent;
use Organization\Domain\Event\Role\{OrganizationRoleAssignedEvent, OrganizationRoleCreatedEvent, OrganizationRoleDeletedEvent, OrganizationRoleUnassignedEvent, OrganizationRoleUpdatedEvent};
use Organization\Domain\Event\Security\{OrganizationLastAdminLockoutPreventedEvent, OrganizationPermissionGrantDeniedEvent};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_keys;
use function count;
use function hash_hmac;
use function sprintf;

/**
 * Test OrganizationAuditWiringTest.
 *
 * End-to-end wiring proof: every Organization domain event,
 * dispatched through the real event-name derivation of
 * SymfonyEventDispatcherAdapter, reaches AuditEventSubscriber
 * and produces the expected audit action, subject and metadata.
 * A drift between a subscription key and the derived event name
 * would otherwise be a silent no-op.
 *
 * @category Event Subscriber Tests
 */
#[CoversClass(className: AuditEventSubscriber::class)]
final class OrganizationAuditWiringTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testEveryOrganizationDomainEventProducesItsAuditRecord(): void
  {
    $events = [
      'organization.created' => new OrganizationCreatedEvent(organizationId: 'org-1', name: 'Acme', ownerUserId: 'user-1'),
      'organization.archived' => new OrganizationArchivedEvent(organizationId: 'org-1'),
      'organization.restored' => new OrganizationRestoredEvent(organizationId: 'org-1', previousStatus: 'archived'),
      'organization.suspended' => new OrganizationSuspendedEvent(organizationId: 'org-1'),
      'organization.settings_updated' => new OrganizationSettingsUpdatedEvent(organizationId: 'org-1', changedFields: ['name', 'slug']),
      'organization.role_created' => new OrganizationRoleCreatedEvent(organizationId: 'org-1', roleId: 'role-1', roleName: 'Managers', permissions: ['organization.read']),
      'organization.role_updated' => new OrganizationRoleUpdatedEvent(organizationId: 'org-1', roleId: 'role-1', roleName: 'Managers', permissions: ['organization.read']),
      'organization.role_deleted' => new OrganizationRoleDeletedEvent(organizationId: 'org-1', roleId: 'role-1', roleName: 'Managers'),
      'organization.role_assigned' => new OrganizationRoleAssignedEvent(organizationId: 'org-1', memberId: 'member-1', roleId: 'role-1', roleName: 'Managers'),
      'organization.role_unassigned' => new OrganizationRoleUnassignedEvent(organizationId: 'org-1', memberId: 'member-1', roleId: 'role-1'),
      'organization.member_added' => new OrganizationMemberAddedEvent(organizationId: 'org-1', memberId: 'member-1', userId: 'user-1', roleIds: ['role-1']),
      'organization.member_removed' => new OrganizationMemberRemovedEvent(organizationId: 'org-1', memberId: 'member-1', userId: 'user-1'),
      'organization.invitation_sent' => new OrganizationInvitationSentEvent(organizationId: 'org-1', invitationId: 'inv-1', invitedEmail: 'a@b.c', invitedByUserId: 'user-1'),
      'organization.invitation_accepted' => new OrganizationInvitationAcceptedEvent(organizationId: 'org-1', invitationId: 'inv-1', memberId: 'member-1', userId: 'user-1', userEmail: 'a@b.c', roleIds: ['role-1']),
      'organization.invitation_revoked' => new OrganizationInvitationRevokedEvent(organizationId: 'org-1', invitationId: 'inv-1', revokedByUserId: 'user-1', reason: 'delivery_failed'),
      'organization.plan_changed' => new OrganizationPlanChangedEvent(organizationId: 'org-1', planId: 'plan-2', previousPlanId: 'plan-1', overQuotaResources: ['members']),
      'organization.permission_grant_denied' => new OrganizationPermissionGrantDeniedEvent(organizationId: 'org-1', actorUserId: 'user-1', deniedPermission: 'organization.delete', context: 'grant_permissions'),
      'organization.last_admin_lockout_prevented' => new OrganizationLastAdminLockoutPreventedEvent(organizationId: 'org-1', attemptedAction: 'remove_member', memberId: 'member-1'),
    ];

    $expected = [
      'organization.created' => ['organization', 'org-1', ['name' => 'Acme', 'owner_user_id' => 'user-1', 'organization_id' => 'org-1']],
      'organization.archived' => ['organization', 'org-1', ['organization_id' => 'org-1']],
      'organization.restored' => ['organization', 'org-1', ['previous_status' => 'archived', 'organization_id' => 'org-1']],
      'organization.suspended' => ['organization', 'org-1', ['organization_id' => 'org-1']],
      'organization.settings_updated' => ['organization', 'org-1', ['changed_fields' => ['name', 'slug'], 'organization_id' => 'org-1']],
      'organization.role_created' => ['organization_role', 'role-1', ['role_name' => 'Managers', 'permissions' => ['organization.read'], 'organization_id' => 'org-1']],
      'organization.role_updated' => ['organization_role', 'role-1', ['role_name' => 'Managers', 'permissions' => ['organization.read'], 'organization_id' => 'org-1']],
      'organization.role_deleted' => ['organization_role', 'role-1', ['role_name' => 'Managers', 'organization_id' => 'org-1']],
      'organization.role_assigned' => ['organization_member', 'member-1', ['role_id' => 'role-1', 'role_name' => 'Managers', 'organization_id' => 'org-1']],
      'organization.role_unassigned' => ['organization_member', 'member-1', ['role_id' => 'role-1', 'organization_id' => 'org-1']],
      'organization.member_added' => ['organization_member', 'member-1', ['user_id' => 'user-1', 'role_ids' => ['role-1'], 'organization_id' => 'org-1']],
      'organization.member_removed' => ['organization_member', 'member-1', ['user_id' => 'user-1', 'organization_id' => 'org-1']],
      'organization.invitation_sent' => ['organization_invitation', 'inv-1', ['invited_email' => 'a@b.c', 'invited_email_hash' => hash_hmac('sha256', 'a@b.c', 'salt-for-tests'), 'resend' => false, 'organization_id' => 'org-1']],
      'organization.invitation_accepted' => ['organization_invitation', 'inv-1', ['member_id' => 'member-1', 'role_ids' => ['role-1'], 'organization_id' => 'org-1']],
      'organization.invitation_revoked' => ['organization_invitation', 'inv-1', ['reason' => 'delivery_failed', 'organization_id' => 'org-1']],
      'organization.plan_changed' => ['organization', 'org-1', ['plan_id' => 'plan-2', 'previous_plan_id' => 'plan-1', 'over_quota_resources' => ['members'], 'organization_id' => 'org-1']],
      'organization.permission_grant_denied' => ['organization', 'org-1', ['denied_permission' => 'organization.delete', 'context' => 'grant_permissions', 'organization_id' => 'org-1']],
      'organization.last_admin_lockout_prevented' => ['organization', 'org-1', ['attempted_action' => 'remove_member', 'member_id' => 'member-1', 'role_id' => null, 'organization_id' => 'org-1']],
    ];

    /** @var list<RecordAuditEventCommand> $recorded */
    $recorded = [];
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willReturnCallback(static function (RecordAuditEventCommand $command) use (&$recorded): RecordAuditEventResult {
        $recorded[] = $command;

        return new RecordAuditEventResult(eventId: 'event-1');
      });

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $security,
      logger: new NullLogger(),
    );

    $symfonyDispatcher = new EventDispatcher();
    $symfonyDispatcher->addSubscriber($subscriber);
    $adapter = new SymfonyEventDispatcherAdapter(
      eventDispatcher: $symfonyDispatcher,
      logger: new NullLogger(),
    );

    foreach ($events as $event) {
      $adapter->dispatch($event);
    }

    self::assertCount(count($expected), $recorded);

    $actions = [];
    foreach ($recorded as $command) {
      $actions[] = $command->action;
      [$subjectType, $subjectId, $metadata] = $expected[$command->action];
      self::assertSame($subjectType, $command->subjectType, sprintf('subjectType mismatch for %s', $command->action));
      self::assertSame($subjectId, $command->subjectId, sprintf('subjectId mismatch for %s', $command->action));
      self::assertSame($metadata, $command->metadata, sprintf('metadata mismatch for %s', $command->action));
    }

    self::assertSame(array_keys($expected), $actions);
  }
  // #endregion
}
