<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\AcceptOrganizationInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Contract\Notification\SentNotification;
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\Service\OrganizationInvitationTokenHasher;
use Organization\Application\UseCase\Command\Organization\AcceptOrganizationInvitation\{AcceptOrganizationInvitationCommand, AcceptOrganizationInvitationHandler, AcceptOrganizationInvitationResult};
use Organization\Application\UseCase\Command\Organization\AddOrganizationMember\AddOrganizationMemberHandler;
use Organization\Domain\Event\Invitation\OrganizationInvitationAcceptedEvent;
use Organization\Domain\Event\Member\OrganizationMemberAddedEvent;
use Organization\Domain\Exception\OrganizationInvitationNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationInvitationStatus, OrganizationMemberId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};
use Shared\Domain\ValueObject\Email;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;

use function hash;
use function is_string;

#[CoversClass(AcceptOrganizationInvitationHandler::class)]
final class AcceptOrganizationInvitationHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeAcceptsInvitationAndSendsNotificationToInviter(): void
  {
    $token = 'plain-token';
    $organizationId = '550e8400-e29b-41d4-a716-446655442100';
    $invitationId = '550e8400-e29b-41d4-a716-446655442101';
    $memberId = '550e8400-e29b-41d4-a716-446655442102';
    $roleId = '550e8400-e29b-41d4-a716-446655442103';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655442104';
    $inviteeUserId = '550e8400-e29b-41d4-a716-446655442105';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId($invitationId),
      organizationId: new OrganizationId($organizationId),
      email: new Email('member@example.com'),
      tokenHash: hash('sha256', $token),
      invitedByUserId: $inviterUserId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())
      ->method('findByTokenHash')
      ->with(hash('sha256', $token))
      ->willReturn($invitation);
    $invitationRepository->expects(self::once())
      ->method('findRoleIdsForInvitation')
      ->willReturn([$roleId]);
    $invitationRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (OrganizationInvitation $updatedInvitation) use ($inviteeUserId): bool {
        return 'accepted' === $updatedInvitation->status()->value
          && $inviteeUserId === $updatedInvitation->acceptedByUserId()
          && $updatedInvitation->acceptedAt() instanceof DateTimeImmutable;
      }));

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::exactly(2))
      ->method('findById')
      ->willReturn(Organization::reconstitute(
        id: new OrganizationId($organizationId),
        name: new OrganizationName('Fireguard HQ'),
        createdByUserId: $inviterUserId,
        isActive: true,
        createdAt: new DateTimeImmutable('-10 days'),
      ));

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($inviteeUserId, 'member@example.com'));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->willReturn(null);
    $memberRepository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(OrganizationMember::class));
    $memberRepository->expects(self::once())
      ->method('assignRole')
      ->with(
        self::callback(static fn (OrganizationMemberId $id): bool => $memberId === (string) $id),
        self::callback(static fn (OrganizationRoleId $id): bool => $roleId === (string) $id),
      );
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->willReturn([$roleId]);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())
      ->method('findByOrganizationAndName');
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([
        OrganizationRole::reconstitute(
          id: new OrganizationRoleId($roleId),
          organizationId: new OrganizationId($organizationId),
          name: new OrganizationRoleName('member'),
          permissions: ['organization.read'],
          isSystem: true,
          createdAt: new DateTimeImmutable('-10 days'),
        ),
      ]);

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OrganizationMemberId::class)
      ->willReturn(new OrganizationMemberId($memberId));

    /** @var TransactionManagerPort&MockObject $addMemberTransactionManager */
    $addMemberTransactionManager = $this->createMock(TransactionManagerPort::class);
    $addMemberTransactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var NotificationPort&MockObject $addMemberNotificationPort */
    $addMemberNotificationPort = $this->createMock(NotificationPort::class);
    $addMemberNotificationPort->expects(self::never())
      ->method('send');

    /** @var LoggerPort&MockObject $addMemberLogger */
    $addMemberLogger = $this->createMock(LoggerPort::class);
    $addMemberLogger->expects(self::never())
      ->method('warning');

    $addOrganizationMemberHandler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      notificationPort: $addMemberNotificationPort,
      logger: $addMemberLogger,
      uuidFactory: $uuidFactory,
      transactionManager: $addMemberTransactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request) use ($inviterUserId, $organizationId, $invitationId, $inviteeUserId): bool {
        return NotificationType::ORGANIZATION_INVITATION_ACCEPTED === $request->type
          && 'Invitation accepted' === $request->subject
          && 'member@example.com accepted your organization invitation.' === $request->body
          && [NotificationChannel::MERCURE] === $request->channels
          && $inviterUserId === $request->recipientUserId
          && null === $request->recipientEmail
          && $organizationId === ($request->payload['organizationId'] ?? null)
          && $invitationId === ($request->payload['invitationId'] ?? null)
          && $inviteeUserId === ($request->payload['acceptedUserId'] ?? null)
          && 'member@example.com' === ($request->payload['acceptedEmail'] ?? null)
          && is_string($request->payload['acceptedAt'] ?? null)
          && $organizationId === $request->organizationId;
      }))
      ->willReturn(new SentNotification(
        id: '550e8400-e29b-41d4-a716-446655449002',
        type: NotificationType::ORGANIZATION_INVITATION_ACCEPTED,
        subject: 'Invitation accepted',
        body: 'member@example.com accepted your organization invitation.',
        channels: [NotificationChannel::MERCURE->value],
        payload: [
          'organizationId' => $organizationId,
          'invitationId' => $invitationId,
          'acceptedUserId' => $inviteeUserId,
          'acceptedEmail' => 'member@example.com',
          'acceptedAt' => '2024-01-01T00:00:00+00:00',
        ],
        channelDelivery: [NotificationChannel::MERCURE->value => true],
        createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        recipientUserId: $inviterUserId,
      ));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())
      ->method('warning');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $dispatchedEvents = [];

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::exactly(2))
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatchedEvents): void {
        $dispatchedEvents[] = $event;
      });

    $handler = new AcceptOrganizationInvitationHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      addOrganizationMemberHandler: $addOrganizationMemberHandler,
      quota: $this->createStub(OrganizationQuotaPort::class),
      notificationPort: $notificationPort,
      logger: $logger,
      transactionManager: $transactionManager,
      tokenHasher: new OrganizationInvitationTokenHasher(),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new AcceptOrganizationInvitationCommand(
      token: $token,
      userId: $inviteeUserId,
      userEmail: 'Member@example.com',
    ));

    self::assertInstanceOf(AcceptOrganizationInvitationResult::class, $result);
    self::assertSame($invitationId, $result->invitationId);
    self::assertSame($memberId, $result->memberId);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame($inviteeUserId, $result->userId);
    self::assertSame([$roleId], $result->roleIds);
    self::assertTrue($result->isActive);
    self::assertInstanceOf(DateTimeImmutable::class, $result->joinedAt);

    // Both audit events are dispatched post-commit: the membership creation
    // first, then the invitation acceptance (each with the granted roles).
    self::assertCount(2, $dispatchedEvents);
    $memberAdded = $dispatchedEvents[0];
    self::assertInstanceOf(OrganizationMemberAddedEvent::class, $memberAdded);
    self::assertSame($organizationId, $memberAdded->organizationId);
    self::assertSame($memberId, $memberAdded->memberId);
    self::assertSame($inviteeUserId, $memberAdded->userId);
    self::assertSame([$roleId], $memberAdded->roleIds);
    $accepted = $dispatchedEvents[1];
    self::assertInstanceOf(OrganizationInvitationAcceptedEvent::class, $accepted);
    self::assertSame($organizationId, $accepted->organizationId);
    self::assertSame($invitationId, $accepted->invitationId);
    self::assertSame($memberId, $accepted->memberId);
    self::assertSame($inviteeUserId, $accepted->userId);
    self::assertSame('Member@example.com', $accepted->userEmail);
    self::assertSame([$roleId], $accepted->roleIds);
  }

  #[Test]
  public function testInvokeAlsoNotifiesOwnerWhenOwnerDiffersFromInviter(): void
  {
    $token = 'plain-token-owner';
    $organizationId = '550e8400-e29b-41d4-a716-446655442110';
    $invitationId = '550e8400-e29b-41d4-a716-446655442111';
    $memberId = '550e8400-e29b-41d4-a716-446655442112';
    $roleId = '550e8400-e29b-41d4-a716-446655442113';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655442114';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655442115';
    $inviteeUserId = '550e8400-e29b-41d4-a716-446655442116';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId($invitationId),
      organizationId: new OrganizationId($organizationId),
      email: new Email('member@example.com'),
      tokenHash: hash('sha256', $token),
      invitedByUserId: $inviterUserId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())
      ->method('findByTokenHash')
      ->with(hash('sha256', $token))
      ->willReturn($invitation);
    $invitationRepository->expects(self::once())
      ->method('findRoleIdsForInvitation')
      ->willReturn([$roleId]);
    $invitationRepository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(OrganizationInvitation::class));

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: $ownerUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('-10 days'),
      updatedAt: new DateTimeImmutable('-5 days'),
      ownerUserId: $ownerUserId,
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::exactly(2))
      ->method('findById')
      ->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($inviteeUserId, 'member@example.com'));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->willReturn(null);
    $memberRepository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(OrganizationMember::class));
    $memberRepository->expects(self::once())
      ->method('assignRole');
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->willReturn([$roleId]);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([
        OrganizationRole::reconstitute(
          id: new OrganizationRoleId($roleId),
          organizationId: new OrganizationId($organizationId),
          name: new OrganizationRoleName('member'),
          permissions: ['organization.read'],
          isSystem: true,
          createdAt: new DateTimeImmutable('-10 days'),
        ),
      ]);

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OrganizationMemberId::class)
      ->willReturn(new OrganizationMemberId($memberId));

    /** @var TransactionManagerPort&MockObject $addMemberTransactionManager */
    $addMemberTransactionManager = $this->createMock(TransactionManagerPort::class);
    $addMemberTransactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var NotificationPort&MockObject $addMemberNotificationPort */
    $addMemberNotificationPort = $this->createMock(NotificationPort::class);
    $addMemberNotificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $addMemberLogger */
    $addMemberLogger = $this->createMock(LoggerPort::class);
    $addMemberLogger->expects(self::never())->method('warning');

    $addOrganizationMemberHandler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      notificationPort: $addMemberNotificationPort,
      logger: $addMemberLogger,
      uuidFactory: $uuidFactory,
      transactionManager: $addMemberTransactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $capturedRequests = [];
    $notificationPort->expects(self::exactly(2))
      ->method('send')
      ->willReturnCallback(function (SendNotificationRequest $request) use (&$capturedRequests, $organizationId, $inviterUserId, $ownerUserId): SentNotification {
        $capturedRequests[] = $request;

        return NotificationType::ORGANIZATION_INVITATION_ACCEPTED === $request->type
          ? new SentNotification(
            id: '550e8400-e29b-41d4-a716-446655449020',
            type: NotificationType::ORGANIZATION_INVITATION_ACCEPTED,
            subject: 'Invitation accepted',
            body: 'member@example.com accepted your organization invitation.',
            channels: [NotificationChannel::MERCURE->value],
            payload: ['organizationId' => $organizationId],
            channelDelivery: [NotificationChannel::MERCURE->value => true],
            createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            recipientUserId: $inviterUserId,
          )
          : new SentNotification(
            id: '550e8400-e29b-41d4-a716-446655449021',
            type: NotificationType::ORGANIZATION_MEMBER_JOINED,
            subject: 'New member joined your organization',
            body: 'member@example.com joined Fireguard HQ.',
            channels: [NotificationChannel::MERCURE->value],
            payload: ['organizationId' => $organizationId],
            channelDelivery: [NotificationChannel::MERCURE->value => true],
            createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            recipientUserId: $ownerUserId,
          );
      });

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $handler = new AcceptOrganizationInvitationHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      addOrganizationMemberHandler: $addOrganizationMemberHandler,
      quota: $this->createStub(OrganizationQuotaPort::class),
      notificationPort: $notificationPort,
      logger: $logger,
      transactionManager: $transactionManager,
      tokenHasher: new OrganizationInvitationTokenHasher(),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $result = $handler->__invoke(new AcceptOrganizationInvitationCommand(
      token: $token,
      userId: $inviteeUserId,
      userEmail: 'member@example.com',
    ));

    self::assertInstanceOf(AcceptOrganizationInvitationResult::class, $result);
    self::assertSame($memberId, $result->memberId);
    self::assertCount(2, $capturedRequests);
    self::assertSame(NotificationType::ORGANIZATION_INVITATION_ACCEPTED, $capturedRequests[0]->type);
    self::assertSame($inviterUserId, $capturedRequests[0]->recipientUserId);
    self::assertSame($organizationId, $capturedRequests[0]->payload['organizationId'] ?? null);
    self::assertSame($invitationId, $capturedRequests[0]->payload['invitationId'] ?? null);
    self::assertSame($inviteeUserId, $capturedRequests[0]->payload['acceptedUserId'] ?? null);
    self::assertSame($organizationId, $capturedRequests[0]->organizationId);
    self::assertSame(NotificationType::ORGANIZATION_MEMBER_JOINED, $capturedRequests[1]->type);
    self::assertSame($ownerUserId, $capturedRequests[1]->recipientUserId);
    self::assertSame($organizationId, $capturedRequests[1]->payload['organizationId'] ?? null);
    self::assertSame($invitationId, $capturedRequests[1]->payload['invitationId'] ?? null);
    self::assertSame($memberId, $capturedRequests[1]->payload['memberId'] ?? null);
    self::assertSame($inviteeUserId, $capturedRequests[1]->payload['joinedUserId'] ?? null);
    self::assertSame($organizationId, $capturedRequests[1]->organizationId);
  }

  #[Test]
  public function testInvokeReturnsResultWhenInviterNotificationFailsButOwnerNotificationStillSends(): void
  {
    $token = 'plain-token-inviter-failure';
    $organizationId = '550e8400-e29b-41d4-a716-446655442130';
    $invitationId = '550e8400-e29b-41d4-a716-446655442131';
    $memberId = '550e8400-e29b-41d4-a716-446655442132';
    $roleId = '550e8400-e29b-41d4-a716-446655442133';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655442134';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655442135';
    $inviteeUserId = '550e8400-e29b-41d4-a716-446655442136';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId($invitationId),
      organizationId: new OrganizationId($organizationId),
      email: new Email('member@example.com'),
      tokenHash: hash('sha256', $token),
      invitedByUserId: $inviterUserId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('findByTokenHash')->willReturn($invitation);
    $invitationRepository->expects(self::once())->method('findRoleIdsForInvitation')->willReturn([$roleId]);
    $invitationRepository->expects(self::once())->method('save')->with(self::isInstanceOf(OrganizationInvitation::class));

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: $ownerUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('-10 days'),
      updatedAt: new DateTimeImmutable('-5 days'),
      ownerUserId: $ownerUserId,
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::exactly(2))->method('findById')->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($inviteeUserId, 'member@example.com'));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findByOrganizationAndUser')->willReturn(null);
    $memberRepository->expects(self::once())->method('save')->with(self::isInstanceOf(OrganizationMember::class));
    $memberRepository->expects(self::once())->method('assignRole');
    $memberRepository->expects(self::once())->method('findRoleIdsForMember')->willReturn([$roleId]);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([
        OrganizationRole::reconstitute(
          id: new OrganizationRoleId($roleId),
          organizationId: new OrganizationId($organizationId),
          name: new OrganizationRoleName('member'),
          permissions: ['organization.read'],
          isSystem: true,
          createdAt: new DateTimeImmutable('-10 days'),
        ),
      ]);

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())->method('create')->with(OrganizationMemberId::class)->willReturn(new OrganizationMemberId($memberId));

    /** @var TransactionManagerPort&MockObject $addMemberTransactionManager */
    $addMemberTransactionManager = $this->createMock(TransactionManagerPort::class);
    $addMemberTransactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var NotificationPort&MockObject $addMemberNotificationPort */
    $addMemberNotificationPort = $this->createMock(NotificationPort::class);
    $addMemberNotificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $addMemberLogger */
    $addMemberLogger = $this->createMock(LoggerPort::class);
    $addMemberLogger->expects(self::never())->method('warning');

    $addOrganizationMemberHandler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      notificationPort: $addMemberNotificationPort,
      logger: $addMemberLogger,
      uuidFactory: $uuidFactory,
      transactionManager: $addMemberTransactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $capturedTypes = [];
    $capturedOrganizationIds = [];
    $notificationPort->expects(self::exactly(2))
      ->method('send')
      ->willReturnCallback(function (SendNotificationRequest $request) use (&$capturedTypes, &$capturedOrganizationIds, $organizationId, $ownerUserId): SentNotification {
        $capturedTypes[] = $request->type;
        $capturedOrganizationIds[] = $request->organizationId;

        if (NotificationType::ORGANIZATION_INVITATION_ACCEPTED === $request->type) {
          throw new RuntimeException('Mercure inviter channel unavailable.');
        }

        return new SentNotification(
          id: '550e8400-e29b-41d4-a716-446655449230',
          type: NotificationType::ORGANIZATION_MEMBER_JOINED,
          subject: 'New member joined your organization',
          body: 'member@example.com joined Fireguard HQ.',
          channels: [NotificationChannel::MERCURE->value],
          payload: ['organizationId' => $organizationId],
          channelDelivery: [NotificationChannel::MERCURE->value => true],
          createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
          recipientUserId: $ownerUserId,
        );
      });

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'Invitation accepted notification dispatch failed.',
        [
          'organizationId' => $organizationId,
          'invitationId' => $invitationId,
          'recipientUserId' => $inviterUserId,
          'error' => 'Mercure inviter channel unavailable.',
        ],
      );

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $handler = new AcceptOrganizationInvitationHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      addOrganizationMemberHandler: $addOrganizationMemberHandler,
      quota: $this->createStub(OrganizationQuotaPort::class),
      notificationPort: $notificationPort,
      logger: $logger,
      transactionManager: $transactionManager,
      tokenHasher: new OrganizationInvitationTokenHasher(),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $result = $handler->__invoke(new AcceptOrganizationInvitationCommand(
      token: $token,
      userId: $inviteeUserId,
      userEmail: 'member@example.com',
    ));

    self::assertInstanceOf(AcceptOrganizationInvitationResult::class, $result);
    self::assertSame([NotificationType::ORGANIZATION_INVITATION_ACCEPTED, NotificationType::ORGANIZATION_MEMBER_JOINED], $capturedTypes);
    self::assertSame([$organizationId, $organizationId], $capturedOrganizationIds);
  }

  #[Test]
  public function testInvokeDoesNotDispatchAcceptedEventWhenInvitationIsExpired(): void
  {
    $token = 'plain-token-expired';
    $organizationId = '550e8400-e29b-41d4-a716-446655442140';
    $invitationId = '550e8400-e29b-41d4-a716-446655442141';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655442142';
    $inviteeUserId = '550e8400-e29b-41d4-a716-446655442143';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId($invitationId),
      organizationId: new OrganizationId($organizationId),
      email: new Email('member@example.com'),
      tokenHash: hash('sha256', $token),
      invitedByUserId: $inviterUserId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('-1 hour'),
      createdAt: new DateTimeImmutable('-8 days'),
      updatedAt: new DateTimeImmutable('-8 days'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())
      ->method('findByTokenHash')
      ->with(hash('sha256', $token))
      ->willReturn($invitation);
    $invitationRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (OrganizationInvitation $updatedInvitation): bool => 'expired' === $updatedInvitation->status()->value));
    $invitationRepository->expects(self::never())
      ->method('findRoleIdsForInvitation');

    $addOrganizationMemberHandler = new AddOrganizationMemberHandler(
      organizationRepository: $this->createStub(OrganizationRepositoryPort::class),
      memberRepository: $this->createStub(OrganizationMemberRepositoryPort::class),
      roleRepository: $this->createStub(OrganizationRoleRepositoryPort::class),
      userRepository: $this->createStub(UserRepositoryPort::class),
      notificationPort: $this->createStub(NotificationPort::class),
      logger: $this->createStub(LoggerPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
      transactionManager: $this->createStub(TransactionManagerPort::class),
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new AcceptOrganizationInvitationHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $this->createStub(OrganizationRepositoryPort::class),
      addOrganizationMemberHandler: $addOrganizationMemberHandler,
      quota: $this->createStub(OrganizationQuotaPort::class),
      notificationPort: $notificationPort,
      logger: $this->createStub(LoggerPort::class),
      transactionManager: $transactionManager,
      tokenHasher: new OrganizationInvitationTokenHasher(),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invitation has expired.');

    $handler->__invoke(new AcceptOrganizationInvitationCommand(
      token: $token,
      userId: $inviteeUserId,
      userEmail: 'member@example.com',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTokenIsBlank(): void
  {
    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::never())->method('findByTokenHash');

    $handler = $this->createHandler($invitationRepository);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invitation token is required.');

    $handler->__invoke(new AcceptOrganizationInvitationCommand(
      token: '   ',
      userId: '550e8400-e29b-41d4-a716-446655442150',
      userEmail: 'member@example.com',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenInvitationIsNotFound(): void
  {
    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())
      ->method('findByTokenHash')
      ->with(hash('sha256', 'missing-token'))
      ->willReturn(null);

    $handler = $this->createHandler($invitationRepository);

    $this->expectException(OrganizationInvitationNotFoundException::class);

    $handler->__invoke(new AcceptOrganizationInvitationCommand(
      token: 'missing-token',
      userId: '550e8400-e29b-41d4-a716-446655442160',
      userEmail: 'member@example.com',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenInvitationIsNoLongerPending(): void
  {
    $token = 'plain-token-revoked';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId('550e8400-e29b-41d4-a716-446655442171'),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655442170'),
      email: new Email('member@example.com'),
      tokenHash: hash('sha256', $token),
      invitedByUserId: '550e8400-e29b-41d4-a716-446655442172',
      status: OrganizationInvitationStatus::REVOKED,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('findByTokenHash')->willReturn($invitation);
    $invitationRepository->expects(self::never())->method('save');

    $handler = $this->createHandler($invitationRepository);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invitation is no longer pending.');

    $handler->__invoke(new AcceptOrganizationInvitationCommand(
      token: $token,
      userId: '550e8400-e29b-41d4-a716-446655442173',
      userEmail: 'member@example.com',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenInvitationEmailDoesNotMatchAuthenticatedUser(): void
  {
    $token = 'plain-token-mismatch';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId('550e8400-e29b-41d4-a716-446655442181'),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655442180'),
      email: new Email('invited@example.com'),
      tokenHash: hash('sha256', $token),
      invitedByUserId: '550e8400-e29b-41d4-a716-446655442182',
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('findByTokenHash')->willReturn($invitation);
    $invitationRepository->expects(self::never())->method('save');

    $handler = $this->createHandler($invitationRepository);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invitation email does not match the authenticated user.');

    $handler->__invoke(new AcceptOrganizationInvitationCommand(
      token: $token,
      userId: '550e8400-e29b-41d4-a716-446655442183',
      userEmail: 'someone-else@example.com',
    ));
  }

  /**
   * Builds a handler whose collaborators all reject work, for the guard clauses
   * that must abort before any membership provisioning happens.
   */
  private function createHandler(OrganizationInvitationRepositoryPort $invitationRepository): AcceptOrganizationInvitationHandler
  {
    $addOrganizationMemberHandler = new AddOrganizationMemberHandler(
      organizationRepository: $this->createStub(OrganizationRepositoryPort::class),
      memberRepository: $this->createStub(OrganizationMemberRepositoryPort::class),
      roleRepository: $this->createStub(OrganizationRoleRepositoryPort::class),
      userRepository: $this->createStub(UserRepositoryPort::class),
      notificationPort: $this->createStub(NotificationPort::class),
      logger: $this->createStub(LoggerPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
      transactionManager: $this->createStub(TransactionManagerPort::class),
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    return new AcceptOrganizationInvitationHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $this->createStub(OrganizationRepositoryPort::class),
      addOrganizationMemberHandler: $addOrganizationMemberHandler,
      quota: $this->createStub(OrganizationQuotaPort::class),
      notificationPort: $notificationPort,
      logger: $this->createStub(LoggerPort::class),
      transactionManager: $transactionManager,
      tokenHasher: new OrganizationInvitationTokenHasher(),
      eventDispatcher: $eventDispatcher,
    );
  }
}
