<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\ReactivateOrganizationMember;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Command\Organization\ReactivateOrganizationMember\{ReactivateOrganizationMemberCommand, ReactivateOrganizationMemberHandler, ReactivateOrganizationMemberResult};
use Organization\Domain\Event\Member\OrganizationMemberAddedEvent;
use Organization\Domain\Exception\{OrganizationArchivedException, OrganizationMemberNotFoundException, OrganizationMemberNotInactiveException, OrganizationNotFoundException, OrganizationQuotaExceededException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, OrganizationQuotaResource, OrganizationStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

#[CoversClass(ReactivateOrganizationMemberHandler::class)]
final class ReactivateOrganizationMemberHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655480501';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655480502';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655480503';

  private const string ROLE_ID = '550e8400-e29b-41d4-a716-446655480504';

  private const string OTHER_ORG_ID = '550e8400-e29b-41d4-a716-446655480599';

  #[Test]
  public function testInvokeReactivatesAnInactiveMemberAndDispatchesMemberAddedEvent(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-30 days'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::USER_ID,
      isActive: false,
      joinedAt: new DateTimeImmutable('-30 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);
    $memberRepository->expects(self::once())->method('save')->with($member);
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->with(self::callback(static fn (OrganizationMemberId $id): bool => self::MEMBER_ID === (string) $id))
      ->willReturn([self::ROLE_ID]);

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())
      ->method('assertCanAdd')
      ->with(self::ORG_ID, OrganizationQuotaResource::MEMBERS);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event): bool {
        return $event instanceof OrganizationMemberAddedEvent
          && self::ORG_ID === $event->organizationId
          && self::MEMBER_ID === $event->memberId
          && self::USER_ID === $event->userId
          && [self::ROLE_ID] === $event->roleIds;
      }));

    $handler = new ReactivateOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      quota: $quota,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new ReactivateOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));

    self::assertInstanceOf(ReactivateOrganizationMemberResult::class, $result);
    self::assertSame(self::MEMBER_ID, $result->memberId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertSame(self::USER_ID, $result->userId);
    self::assertSame([self::ROLE_ID], $result->roleIds);
    self::assertTrue($result->isActive);
    self::assertTrue($member->isActive());
  }

  #[Test]
  public function testInvokeThrowsQuotaExceededAndLeavesTheMemberInactiveWhenTheMemberCapIsReached(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-30 days'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::USER_ID,
      isActive: false,
      joinedAt: new DateTimeImmutable('-30 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);
    $memberRepository->expects(self::never())->method('save');
    $memberRepository->expects(self::never())->method('findRoleIdsForMember');

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())
      ->method('assertCanAdd')
      ->with(self::ORG_ID, OrganizationQuotaResource::MEMBERS)
      ->willThrowException(OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::MEMBERS, 5));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new ReactivateOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      quota: $quota,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationQuotaExceededException::class);

    $handler->__invoke(new ReactivateOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));

    self::assertFalse($member->isActive(), 'The member must stay inactive when the quota check rejects the reactivation.');
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(null);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('findById');
    $memberRepository->expects(self::never())->method('save');

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new ReactivateOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      quota: $quota,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new ReactivateOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationIsArchived(): void
  {
    $archivedOrganization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: false,
      createdAt: new DateTimeImmutable('-30 days'),
      status: OrganizationStatus::ARCHIVED,
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($archivedOrganization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('findById');
    $memberRepository->expects(self::never())->method('save');

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new ReactivateOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      quota: $quota,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationArchivedException::class);

    $handler->__invoke(new ReactivateOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMemberNotFound(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-30 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn(null);
    $memberRepository->expects(self::never())->method('save');

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new ReactivateOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      quota: $quota,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new ReactivateOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMemberDoesNotBelongToOrganization(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-30 days'),
    );

    $memberFromAnotherOrg = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::OTHER_ORG_ID),
      userId: self::USER_ID,
      isActive: false,
      joinedAt: new DateTimeImmutable('-30 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($memberFromAnotherOrg);
    $memberRepository->expects(self::never())->method('save');

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new ReactivateOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      quota: $quota,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new ReactivateOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMemberIsAlreadyActive(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-30 days'),
    );

    $activeMember = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-30 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($activeMember);
    $memberRepository->expects(self::never())->method('save');

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new ReactivateOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      quota: $quota,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationMemberNotInactiveException::class);

    $handler->__invoke(new ReactivateOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  /**
   * The transaction manager is a real fake rather than a mock: the handler's
   * unit-of-work closure must actually run for the quota check and the
   * member activation/save to be observed.
   */
  private function fakeTransactionManager(): TransactionManagerPort
  {
    return new class () implements TransactionManagerPort {
      public function transactional(callable $operation): mixed
      {
        return $operation();
      }
    };
  }
}
