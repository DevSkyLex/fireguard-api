<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\InviteOrganizationMember;

use DateTimeImmutable;
use Notification\Application\Port\Inbound\NotificationPort;
use Onboarding\Application\Contract\Setup\{OrganizationSetupContext, OrganizationSetupOperation};
use Onboarding\Application\Port\Inbound\OrganizationSetupPort;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\Service\{OrganizationInvitationNotifier, OrganizationInvitationTokenHasher};
use Organization\Application\UseCase\Command\Organization\InviteOrganizationMember\{InviteOrganizationMemberCommand, InviteOrganizationMemberHandler};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};
use Shared\Domain\ValueObject\Email;
use Tests\Support\Factory\EmailTranslatorTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;

/**
 * Durable organization setup recovery.
 *
 * @category UnitTest
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InviteOrganizationMemberSetupTest extends TestCase
{
  #[Test]
  public function replayReturnsExistingInvitationWithoutEmailTokenQuotaOrEvent(): void
  {
    $orgId = 'cc11c711-0000-4000-8000-000000000201';
    $id = 'cc11c711-0000-4000-8000-000000000202';
    $roleId = 'cc11c711-0000-4000-8000-000000000203';
    $org = Organization::reconstitute(id: OrganizationId::fromString($orgId), name: new OrganizationName('Saved org'), createdByUserId: 'creator', isActive: true, createdAt: new DateTimeImmutable('-1 day'));
    $role = OrganizationRole::create(OrganizationRoleId::fromString($roleId), OrganizationId::fromString($orgId), new OrganizationRoleName('member'), ['organization.read'], true);
    $invitation = OrganizationInvitation::create(OrganizationInvitationId::fromString($id), OrganizationId::fromString($orgId), new Email('member@example.com'), 'persisted-hash', 'creator', new DateTimeImmutable('+1 day'));
    $orgs = $this->createStub(OrganizationRepositoryPort::class);
    $orgs->method('findById')->willReturn($org);
    $roles = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roles->method('findByOrganizationAndName')->willReturn($role);
    $roles->method('findByIdsInOrganization')->willReturn([$role]);
    $invitations = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitations->method('findById')->willReturn($invitation);
    $invitations->method('findRoleIdsForInvitation')->willReturn([$roleId]);
    $invitations->expects(self::never())->method('save');
    $invitations->expects(self::never())->method('findPendingByOrganizationAndEmail');
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');
    $setup = $this->createMock(OrganizationSetupPort::class);
    $setup->method('begin')->willReturn(new OrganizationSetupOperation('invite_members', 'invite', ['email' => 'member@example.com'], $id));
    $setup->expects(self::never())->method('complete');
    $uuid = $this->createStub(UuidFactory::class);
    $uuid->method('create')->willReturn(OrganizationInvitationId::fromString('cc11c711-0000-4000-8000-000000000299'));
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());
    $handler = new InviteOrganizationMemberHandler($orgs, $roles, $this->createStub(OrganizationMemberRepositoryPort::class), $invitations, $this->createStub(UserRepositoryPort::class), new OrganizationInvitationNotifier($notifications, 'http://localhost:4200', new OrganizationInvitationTokenHasher(), EmailTranslatorTestFactory::create()), $this->createStub(LoggerPort::class), $uuid, $transactions, $quota, $events, $setup);
    $result = $handler(new InviteOrganizationMemberCommand($orgId, 'member@example.com', 'creator', setupContext: new OrganizationSetupContext('creator', 'session', 'invite')));
    self::assertSame($id, $result->invitationId);
    self::assertSame('pending', $result->status);
    self::assertSame('', $result->acceptUrl);
  }
}
