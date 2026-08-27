<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\AddOrganizationMember;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Contract\Quota\{OrganizationQuotaExceededException, OrganizationQuotaResource};
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\AddOrganizationMember\{AddOrganizationMemberCommand, AddOrganizationMemberHandler, AddOrganizationMemberResult};
use Organization\Domain\Event\Member\OrganizationMemberAddedEvent;
use Organization\Domain\Event\Role\OrganizationRoleAssignedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\Exception\OrganizationUserNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;

use function count;
use function is_string;

#[CoversClass(AddOrganizationMemberHandler::class)]
final class AddOrganizationMemberHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeCreatesMemberAndAssignsDefaultRoleWhenNoRolesProvided(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440100';
    $memberId = '550e8400-e29b-41d4-a716-446655440101';
    $userId = '550e8400-e29b-41d4-a716-446655440102';
    $defaultRoleId = '550e8400-e29b-41d4-a716-446655440103';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $defaultRole = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($defaultRoleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('member'),
      permissions: ['organization.read'],
      isSystem: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($userId, 'member@example.com'));

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
        self::callback(static fn (OrganizationRoleId $id): bool => $defaultRoleId === (string) $id),
      );
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->willReturn([$defaultRoleId]);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->with(
        self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id),
        self::callback(static fn (OrganizationRoleName $name): bool => 'member' === (string) $name),
      )
      ->willReturn($defaultRole);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([$defaultRole]);

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OrganizationMemberId::class)
      ->willReturn(new OrganizationMemberId($memberId));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request) use ($organizationId, $memberId, $userId, $defaultRoleId): bool {
        return NotificationType::ORGANIZATION_MEMBER_ADDED === $request->type
          && 'You have been added to Fireguard Paris' === $request->subject
          && 'You now have access to Fireguard Paris.' === $request->body
          && [NotificationChannel::EMAIL, NotificationChannel::MERCURE] === $request->channels
          && $organizationId === ($request->payload['organizationId'] ?? null)
          && $memberId === ($request->payload['memberId'] ?? null)
          && 'Fireguard Paris' === ($request->payload['organizationName'] ?? null)
          && [$defaultRoleId] === ($request->payload['roleIds'] ?? null)
          && is_string($request->payload['joinedAt'] ?? null)
          && $userId === $request->recipientUserId
          && 'member@example.com' === $request->recipientEmail
          && $organizationId === $request->organizationId;
      }))
      ->willReturn(new SentNotification(
        id: '550e8400-e29b-41d4-a716-446655449010',
        type: NotificationType::ORGANIZATION_MEMBER_ADDED,
        subject: 'You have been added to Fireguard Paris',
        body: 'You now have access to Fireguard Paris.',
        channels: [NotificationChannel::EMAIL->value, NotificationChannel::MERCURE->value],
        payload: [
          'organizationId' => $organizationId,
          'memberId' => $memberId,
          'organizationName' => 'Fireguard Paris',
          'roleIds' => [$defaultRoleId],
          'joinedAt' => '2024-01-01T00:00:00+00:00',
        ],
        channelDelivery: [
          NotificationChannel::EMAIL->value => true,
          NotificationChannel::MERCURE->value => true,
        ],
        createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        recipientUserId: $userId,
        recipientEmail: 'member@example.com',
      ));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event) use ($organizationId, $memberId, $userId): bool {
        return $event instanceof OrganizationMemberAddedEvent
          && $organizationId === $event->organizationId
          && $memberId === $event->memberId
          && $userId === $event->userId;
      }));

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      roleIds: [],
    ));

    self::assertInstanceOf(AddOrganizationMemberResult::class, $result);
    self::assertSame($memberId, $result->memberId);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame($userId, $result->userId);
    self::assertSame([$defaultRoleId], $result->roleIds);
    self::assertTrue($result->isActive);
    self::assertInstanceOf(DateTimeImmutable::class, $result->joinedAt);
  }

  #[Test]
  public function testInvokeReactivatesExistingInactiveMemberBeforeAssigningRoles(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440810';
    $memberId = '550e8400-e29b-41d4-a716-446655440811';
    $userId = '550e8400-e29b-41d4-a716-446655440812';
    $roleId = '550e8400-e29b-41d4-a716-446655440813';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $inactiveMember = OrganizationMember::reconstitute(
      id: new OrganizationMemberId($memberId),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: false,
      joinedAt: new DateTimeImmutable('-5 days'),
    );

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('technician'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($userId));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->willReturn($inactiveMember);
    $memberRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (OrganizationMember $member): bool => $member->isActive()));
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
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([$role]);

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event) use ($organizationId, $memberId, $userId): bool {
        return $event instanceof OrganizationMemberAddedEvent
          && $organizationId === $event->organizationId
          && $memberId === $event->memberId
          && $userId === $event->userId;
      }));

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      uuidFactory: $this->createStub(UuidFactory::class),
      transactionManager: $transactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      roleIds: [$roleId],
      sendMemberNotification: false,
    ));

    self::assertTrue($result->isActive);
    self::assertSame($userId, $result->userId);
    self::assertSame([$roleId], $result->roleIds);
  }

  #[Test]
  public function testInvokeDeduplicatesRoleIdsBeforeLookupAndAssignment(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440500';
    $memberId = '550e8400-e29b-41d4-a716-446655440501';
    $userId = '550e8400-e29b-41d4-a716-446655440502';
    $roleId = '550e8400-e29b-41d4-a716-446655440503';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Lille'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId($memberId),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('technician'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($userId));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->willReturn($member);
    $memberRepository->expects(self::never())->method('save');
    $memberRepository->expects(self::once())
      ->method('assignRole')
      ->with(
        self::callback(static fn (OrganizationMemberId $id): bool => $memberId === (string) $id),
        self::callback(static fn (OrganizationRoleId $id): bool => $roleId === (string) $id),
      );
    $memberRepository->expects(self::exactly(2))
      ->method('findRoleIdsForMember')
      ->willReturnOnConsecutiveCalls([], [$roleId]);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->with(
        self::isInstanceOf(OrganizationId::class),
        self::callback(static function (array $roleIds) use ($roleId): bool {
          return 1 === count($roleIds)
            && $roleIds[0] instanceof OrganizationRoleId
            && $roleId === (string) $roleIds[0];
        }),
      )
      ->willReturn([$role]);

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    // The user is already an active member: the operation is a role grant, so
    // the handler audits the newly assigned role instead of member_added.
    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $event): bool => $event instanceof OrganizationRoleAssignedEvent
        && $organizationId === $event->organizationId
        && $memberId === $event->memberId
        && $roleId === $event->roleId
        && 'technician' === $event->roleName));

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      uuidFactory: $this->createStub(UuidFactory::class),
      transactionManager: $transactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      roleIds: [$roleId, $roleId],
    ));

    self::assertSame([$roleId], $result->roleIds);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationDoesNotExist(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $this->createStub(OrganizationMemberRepositoryPort::class),
      roleRepository: $this->createStub(OrganizationRoleRepositoryPort::class),
      userRepository: $this->createStub(UserRepositoryPort::class),
      notificationPort: $notificationPort,
      logger: $logger,
      uuidFactory: $this->createStub(UuidFactory::class),
      transactionManager: $transactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440100',
      userId: '550e8400-e29b-41d4-a716-446655440102',
      roleIds: [],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTheUserDoesNotExist(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440100';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Test'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);

    $userRepository = $this->createStub(UserRepositoryPort::class);
    $userRepository->method('findById')->willReturn(null);

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $this->createStub(OrganizationMemberRepositoryPort::class),
      roleRepository: $this->createStub(OrganizationRoleRepositoryPort::class),
      userRepository: $userRepository,
      notificationPort: $this->createStub(NotificationPort::class),
      logger: $this->createStub(LoggerPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
      transactionManager: $transactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(OrganizationUserNotFoundException::class);
    $this->expectExceptionMessage('User not found.');

    $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: '550e8400-e29b-41d4-a716-446655440102',
      roleIds: [],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTheDefaultMemberRoleIsMissing(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440100';
    $userId = '550e8400-e29b-41d4-a716-446655440102';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Test'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);

    $userRepository = $this->createStub(UserRepositoryPort::class);
    $userRepository->method('findById')->willReturn(UserTestFactory::createActive($userId));

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->with(
        self::isInstanceOf(OrganizationId::class),
        self::callback(static fn (OrganizationRoleName $name): bool => 'member' === (string) $name),
      )
      ->willReturn(null);

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $this->createStub(OrganizationMemberRepositoryPort::class),
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      notificationPort: $this->createStub(NotificationPort::class),
      logger: $this->createStub(LoggerPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
      transactionManager: $transactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(OrganizationRoleNotFoundException::class);

    $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      roleIds: [],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOneRequestedRoleIsMissing(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440100';
    $userId = '550e8400-e29b-41d4-a716-446655440102';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($userId));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('assignRole');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([]);

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      uuidFactory: $this->createStub(UuidFactory::class),
      transactionManager: $transactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(OrganizationRoleNotFoundException::class);

    $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      roleIds: ['550e8400-e29b-41d4-a716-446655440300'],
    ));
  }

  #[Test]
  public function testInvokeReturnsResultWhenNotificationDispatchFailsForNewMember(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440920';
    $memberId = '550e8400-e29b-41d4-a716-446655440921';
    $userId = '550e8400-e29b-41d4-a716-446655440922';
    $roleId = '550e8400-e29b-41d4-a716-446655440923';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Bordeaux'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('member'),
      permissions: ['organization.read'],
      isSystem: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($userId, 'member@example.com'));

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
      ->method('findByOrganizationAndName')
      ->willReturn($role);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([$role]);

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OrganizationMemberId::class)
      ->willReturn(new OrganizationMemberId($memberId));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->willThrowException(new RuntimeException('Mailer unavailable.'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'Organization member added notification dispatch failed.',
        [
          'organizationId' => $organizationId,
          'memberId' => $memberId,
          'recipientUserId' => $userId,
          'error' => 'Mailer unavailable.',
        ],
      );

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $result = $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      roleIds: [],
    ));

    self::assertInstanceOf(AddOrganizationMemberResult::class, $result);
    self::assertSame($memberId, $result->memberId);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame($userId, $result->userId);
    self::assertSame([$roleId], $result->roleIds);
  }

  #[Test]
  public function testInvokeSkipsQuotaAssertionWhenEnforceQuotaIsFalse(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-4466554408a0';
    $userId = '550e8400-e29b-41d4-a716-4466554408a2';
    $roleId = '550e8400-e29b-41d4-a716-4466554408a3';

    [$organizationRepository, $userRepository, $memberRepository, $roleRepository] = $this->activeMemberFixtures(
      $organizationId,
      '550e8400-e29b-41d4-a716-4466554408a1',
      $userId,
      $roleId,
    );

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      notificationPort: $this->createStub(NotificationPort::class),
      logger: $this->createStub(LoggerPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
      transactionManager: $this->passthroughTransactionManager(),
      quota: $quota,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      roleIds: [$roleId],
      sendMemberNotification: false,
      enforceQuota: false,
    ));
  }

  #[Test]
  public function testInvokePropagatesQuotaExceededAndSkipsMemberWriteWhenEnforced(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-4466554408b0';
    $userId = '550e8400-e29b-41d4-a716-4466554408b2';
    $roleId = '550e8400-e29b-41d4-a716-4466554408b3';

    // The cap is checked as the first statement inside the transaction, before any
    // member lookup/assignment, so the exception surfaces without a member write.
    [$organizationRepository, $userRepository, $memberRepository, $roleRepository] = $this->activeMemberFixtures(
      $organizationId,
      '550e8400-e29b-41d4-a716-4466554408b1',
      $userId,
      $roleId,
    );

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())
      ->method('assertCanAdd')
      ->with($organizationId, OrganizationQuotaResource::MEMBERS)
      ->willThrowException(OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::MEMBERS->value, 5));

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      notificationPort: $this->createStub(NotificationPort::class),
      logger: $this->createStub(LoggerPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
      transactionManager: $this->passthroughTransactionManager(),
      quota: $quota,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(OrganizationQuotaExceededException::class);

    $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      roleIds: [$roleId],
    ));
  }

  #[Test]
  public function testInvokeDoesNotDispatchMemberAddedEventWhenMemberIsAlreadyActive(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-4466554408c0';
    $userId = '550e8400-e29b-41d4-a716-4466554408c2';
    $roleId = '550e8400-e29b-41d4-a716-4466554408c3';

    [$organizationRepository, $userRepository, $memberRepository, $roleRepository] = $this->activeMemberFixtures(
      $organizationId,
      '550e8400-e29b-41d4-a716-4466554408c1',
      $userId,
      $roleId,
    );

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      notificationPort: $this->createStub(NotificationPort::class),
      logger: $this->createStub(LoggerPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
      transactionManager: $this->passthroughTransactionManager(),
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      roleIds: [$roleId],
      sendMemberNotification: false,
    ));

    self::assertTrue($result->isActive);
  }

  /**
   * Builds the organization/user/member/role repository stubs for an existing
   * active member so the quota-branch tests can focus on the enforceQuota flag.
   *
   * @return array{OrganizationRepositoryPort, UserRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRoleRepositoryPort}
   */
  private function activeMemberFixtures(string $organizationId, string $memberId, string $userId, string $roleId): array
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Test'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId($memberId),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('technician'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);

    $userRepository = $this->createStub(UserRepositoryPort::class);
    $userRepository->method('findById')->willReturn(UserTestFactory::createActive($userId));

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationAndUser')->willReturn($member);
    $memberRepository->method('findRoleIdsForMember')->willReturn([$roleId]);

    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findByIdsInOrganization')->willReturn([$role]);

    return [$organizationRepository, $userRepository, $memberRepository, $roleRepository];
  }

  private function passthroughTransactionManager(): TransactionManagerPort
  {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return $transactionManager;
  }
}
