<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\LeaveOrganization;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationLastAdminGuardPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Command\Organization\LeaveOrganization\{LeaveOrganizationCommand, LeaveOrganizationHandler, LeaveOrganizationResult};
use Organization\Domain\Event\Member\OrganizationMemberRemovedEvent;
use Organization\Domain\Exception\{OrganizationLastAdminException, OrganizationMemberNotFoundException, OrganizationNotFoundException, OrganizationOwnerCannotLeaveException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(LeaveOrganizationHandler::class)]
final class LeaveOrganizationHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440600';

  private const string OWNER_ID = '550e8400-e29b-41d4-a716-446655440601';

  private const string MEMBER_USER_ID = '550e8400-e29b-41d4-a716-446655440602';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655440603';

  #[Test]
  public function testInvokeDeactivatesMembershipAndDispatchesEvent(): void
  {
    $organization = $this->activeOrganization();
    $member = $this->activeMember();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->with(self::isInstanceOf(OrganizationId::class), self::MEMBER_USER_ID)
      ->willReturn($member);
    $memberRepository->expects(self::once())->method('save')->with($member);

    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanRemoveMember')
      ->with(self::ORG_ID, self::MEMBER_ID);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event): bool {
        return $event instanceof OrganizationMemberRemovedEvent
          && self::ORG_ID === $event->organizationId
          && self::MEMBER_ID === $event->memberId
          && self::MEMBER_USER_ID === $event->userId;
      }));

    $handler = new LeaveOrganizationHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      lastAdminGuard: $lastAdminGuard,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new LeaveOrganizationCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::MEMBER_USER_ID,
    ));

    self::assertInstanceOf(LeaveOrganizationResult::class, $result);
    self::assertSame(self::MEMBER_ID, $result->memberId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertFalse($member->isActive());
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(null);

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('findByOrganizationAndUser');

    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanRemoveMember');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new LeaveOrganizationHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      lastAdminGuard: $lastAdminGuard,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new LeaveOrganizationCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::MEMBER_USER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenActingUserIsNotAnActiveMember(): void
  {
    $organization = $this->activeOrganization();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findByOrganizationAndUser')->willReturn(null);
    $memberRepository->expects(self::never())->method('save');

    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanRemoveMember');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new LeaveOrganizationHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      lastAdminGuard: $lastAdminGuard,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new LeaveOrganizationCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::MEMBER_USER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMembershipIsAlreadyInactive(): void
  {
    $organization = $this->activeOrganization();

    $inactiveMember = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::MEMBER_USER_ID,
      isActive: false,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findByOrganizationAndUser')->willReturn($inactiveMember);
    $memberRepository->expects(self::never())->method('save');

    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanRemoveMember');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new LeaveOrganizationHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      lastAdminGuard: $lastAdminGuard,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new LeaveOrganizationCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::MEMBER_USER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenActingUserIsTheOwner(): void
  {
    $organization = $this->activeOrganization();

    $ownerMember = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::OWNER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findByOrganizationAndUser')->willReturn($ownerMember);
    $memberRepository->expects(self::never())->method('save');

    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanRemoveMember');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new LeaveOrganizationHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      lastAdminGuard: $lastAdminGuard,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationOwnerCannotLeaveException::class);

    $handler->__invoke(new LeaveOrganizationCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::OWNER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenLeavingWouldStripLastAdministrator(): void
  {
    $organization = $this->activeOrganization();
    $member = $this->activeMember();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findByOrganizationAndUser')->willReturn($member);
    $memberRepository->expects(self::never())->method('save');

    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanRemoveMember')
      ->with(self::ORG_ID, self::MEMBER_ID)
      ->willThrowException(OrganizationLastAdminException::cannotRemoveLastAdmin());

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new LeaveOrganizationHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      lastAdminGuard: $lastAdminGuard,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationLastAdminException::class);

    try {
      $handler->__invoke(new LeaveOrganizationCommand(
        organizationId: self::ORG_ID,
        actingUserId: self::MEMBER_USER_ID,
      ));
    } finally {
      self::assertTrue($member->isActive(), 'a refused leave must never deactivate the membership');
    }
  }

  private function activeOrganization(): Organization
  {
    return Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Marseille'),
      createdByUserId: self::OWNER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
      ownerUserId: self::OWNER_ID,
    );
  }

  private function activeMember(): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::MEMBER_USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );
  }
}
