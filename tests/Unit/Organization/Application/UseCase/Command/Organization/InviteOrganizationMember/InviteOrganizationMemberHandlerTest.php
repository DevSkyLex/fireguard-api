<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\InviteOrganizationMember;

use DateTimeImmutable;
use InvalidArgumentException;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Contract\Quota\{OrganizationQuotaExceededException, OrganizationQuotaResource};
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\Service\{OrganizationInvitationNotifier, OrganizationInvitationTokenHasher};
use Organization\Application\UseCase\Command\Organization\InviteOrganizationMember\{InviteOrganizationMemberCommand, InviteOrganizationMemberHandler, InviteOrganizationMemberResult};
use Organization\Domain\Event\Invitation\{OrganizationInvitationRevokedEvent, OrganizationInvitationSentEvent};
use Organization\Domain\Exception\{
  OrganizationNotFoundException,
  OrganizationRoleNotFoundException
};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{
  OrganizationId,
  OrganizationInvitationId,
  OrganizationMemberId,
  OrganizationName,
  OrganizationRoleId,
  OrganizationRoleName
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use Tests\Support\Factory\EmailTranslatorTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

use function array_key_exists;
use function is_array;
use function is_string;
use function str_contains;

#[CoversClass(InviteOrganizationMemberHandler::class)]
final class InviteOrganizationMemberHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441940';

  private const string INVITATION_ID = '550e8400-e29b-41d4-a716-446655441941';

  private const string INVITER_USER_ID = '550e8400-e29b-41d4-a716-446655441943';

  private const string EXISTING_USER_ID = '550e8400-e29b-41d4-a716-446655441944';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441945';

  #[Test]
  public function testInvokeCreatesInvitationAndSendsNotification(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441900';
    $invitationId = '550e8400-e29b-41d4-a716-446655441901';
    $roleId = '550e8400-e29b-41d4-a716-446655441902';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655441903';
    $email = 'member@example.com';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: $inviterUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $defaultRole = OrganizationRole::reconstitute(
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

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->willReturn($defaultRole);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([$defaultRole]);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())
      ->method('findByOrganizationAndUser');

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $persistedInvitation = null;
    $invitationRepository->expects(self::once())
      ->method('findPendingByOrganizationAndEmail')
      ->willReturn(null);
    $invitationRepository->expects(self::exactly(2))
      ->method('save')
      ->willReturnCallback(static function (OrganizationInvitation $invitation) use (&$persistedInvitation): void {
        if (!$persistedInvitation instanceof OrganizationInvitation) {
          $persistedInvitation = $invitation;
        }
      });
    $invitationRepository->expects(self::once())
      ->method('findById')
      ->willReturnCallback(static function () use (&$persistedInvitation): ?OrganizationInvitation {
        return $persistedInvitation;
      });
    $invitationRepository->expects(self::once())
      ->method('replaceRoleIds');
    $invitationRepository->expects(self::exactly(2))
      ->method('findRoleIdsForInvitation')
      ->willReturn([$roleId]);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findByEmail')
      ->willReturn(null);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request) use ($email, $organizationId): bool {
        $emailPayload = $request->deliveryPayload['email'] ?? null;
        $emailTemplate = is_array($emailPayload) ? ($emailPayload['template'] ?? null) : null;
        $emailContext = is_array($emailPayload) ? ($emailPayload['context'] ?? null) : null;
        $emailAcceptUrl = is_array($emailContext) ? ($emailContext['acceptUrl'] ?? null) : null;

        return 'organization.invitation' === $request->type
          && [NotificationChannel::EMAIL] === $request->channels
          && null === $request->recipientUserId
          && $email === $request->recipientEmail
          && !array_key_exists('token', $request->payload)
          && !array_key_exists('acceptUrl', $request->payload)
          && !str_contains($request->body, 'Use this token')
          && str_contains($request->subject, 'Invitation to join')
          && 'notification/email/organization_invitation.html.twig' === $emailTemplate
          && is_array($emailContext)
          && !array_key_exists('token', $emailContext)
          && is_string($emailAcceptUrl)
          && str_contains($emailAcceptUrl, '/organizations/invitations/accept?token=')
          && $organizationId === $request->organizationId;
      }))
      ->willThrowException(new RuntimeException('Notification delivery failed.'));

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OrganizationInvitationId::class)
      ->willReturn(new OrganizationInvitationId($invitationId));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::exactly(2))
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::exactly(2))
      ->method('warning');

    /** @var list<object> $dispatchedEvents */
    $dispatchedEvents = [];

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::exactly(2))
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatchedEvents): void {
        $dispatchedEvents[] = $event;
      });

    $invitationNotifier = new OrganizationInvitationNotifier(
      $notificationPort,
      'http://localhost:4200',
      new OrganizationInvitationTokenHasher(),
      EmailTranslatorTestFactory::create(),
    );

    $handler = new InviteOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
      invitationRepository: $invitationRepository,
      userRepository: $userRepository,
      invitationNotifier: $invitationNotifier,
      logger: $logger,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new InviteOrganizationMemberCommand(
      organizationId: $organizationId,
      email: $email,
      invitedByUserId: $inviterUserId,
      roleIds: [],
    ));

    self::assertInstanceOf(InviteOrganizationMemberResult::class, $result);
    self::assertSame($invitationId, $result->invitationId);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame($email, $result->email);
    self::assertSame('revoked', $result->status);
    self::assertSame($inviterUserId, $result->invitedByUserId);
    self::assertSame([$roleId], $result->roleIds);

    self::assertCount(2, $dispatchedEvents);

    $sentEvent = $dispatchedEvents[0];
    self::assertInstanceOf(OrganizationInvitationSentEvent::class, $sentEvent);
    self::assertSame($organizationId, $sentEvent->organizationId);
    self::assertSame($invitationId, $sentEvent->invitationId);
    self::assertSame($email, $sentEvent->invitedEmail);
    self::assertSame($inviterUserId, $sentEvent->invitedByUserId);
    self::assertFalse($sentEvent->resend);

    $revokedEvent = $dispatchedEvents[1];
    self::assertInstanceOf(OrganizationInvitationRevokedEvent::class, $revokedEvent);
    self::assertSame($organizationId, $revokedEvent->organizationId);
    self::assertSame($invitationId, $revokedEvent->invitationId);
    self::assertSame($inviterUserId, $revokedEvent->revokedByUserId);
    self::assertSame('delivery_failed', $revokedEvent->reason);
  }

  #[Test]
  public function testInvokeDoesNotDispatchRevokedEventWhenDeliverySucceeds(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441920';
    $invitationId = '550e8400-e29b-41d4-a716-446655441921';
    $roleId = '550e8400-e29b-41d4-a716-446655441922';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655441923';
    $email = 'member@example.com';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: $inviterUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $defaultRole = OrganizationRole::reconstitute(
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

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->willReturn($defaultRole);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([$defaultRole]);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())
      ->method('findByOrganizationAndUser');

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())
      ->method('findPendingByOrganizationAndEmail')
      ->willReturn(null);
    $invitationRepository->expects(self::once())
      ->method('save');
    $invitationRepository->expects(self::never())
      ->method('findById');
    $invitationRepository->expects(self::once())
      ->method('replaceRoleIds');
    $invitationRepository->expects(self::once())
      ->method('findRoleIdsForInvitation')
      ->willReturn([$roleId]);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findByEmail')
      ->willReturn(null);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->willReturn(new SentNotification(
        id: '550e8400-e29b-41d4-a716-446655441924',
        type: 'organization.invitation',
        subject: 'Invitation to join Fireguard HQ',
        body: '<p>You have been invited.</p>',
        channels: [NotificationChannel::EMAIL->value],
        payload: [],
        channelDelivery: [NotificationChannel::EMAIL->value => true],
        createdAt: new DateTimeImmutable(),
        recipientEmail: $email,
      ));

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OrganizationInvitationId::class)
      ->willReturn(new OrganizationInvitationId($invitationId));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())
      ->method('warning');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof OrganizationInvitationSentEvent
          && $organizationId === $event->organizationId
          && $invitationId === $event->invitationId
          && $email === $event->invitedEmail
          && $inviterUserId === $event->invitedByUserId
          && false === $event->resend,
      ));

    $invitationNotifier = new OrganizationInvitationNotifier(
      $notificationPort,
      'http://localhost:4200',
      new OrganizationInvitationTokenHasher(),
      EmailTranslatorTestFactory::create(),
    );

    $handler = new InviteOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
      invitationRepository: $invitationRepository,
      userRepository: $userRepository,
      invitationNotifier: $invitationNotifier,
      logger: $logger,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new InviteOrganizationMemberCommand(
      organizationId: $organizationId,
      email: $email,
      invitedByUserId: $inviterUserId,
      roleIds: [],
    ));

    self::assertInstanceOf(InviteOrganizationMemberResult::class, $result);
    self::assertSame($invitationId, $result->invitationId);
    self::assertSame('pending', $result->status);
    self::assertStringContainsString('/organizations/invitations/accept?token=', $result->acceptUrl);
  }

  #[Test]
  public function testInvokeDoesNotDispatchRevokedEventWhenInvalidationReturnsNull(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441930';
    $invitationId = '550e8400-e29b-41d4-a716-446655441931';
    $roleId = '550e8400-e29b-41d4-a716-446655441932';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655441933';
    $email = 'member@example.com';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: $inviterUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $defaultRole = OrganizationRole::reconstitute(
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

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->willReturn($defaultRole);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([$defaultRole]);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())
      ->method('findByOrganizationAndUser');

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())
      ->method('findPendingByOrganizationAndEmail')
      ->willReturn(null);
    $invitationRepository->expects(self::once())
      ->method('save');
    $invitationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);
    $invitationRepository->expects(self::once())
      ->method('replaceRoleIds');
    $invitationRepository->expects(self::once())
      ->method('findRoleIdsForInvitation')
      ->willReturn([$roleId]);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findByEmail')
      ->willReturn(null);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->willThrowException(new RuntimeException('Notification delivery failed.'));

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OrganizationInvitationId::class)
      ->willReturn(new OrganizationInvitationId($invitationId));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::exactly(2))
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::exactly(2))
      ->method('warning');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof OrganizationInvitationSentEvent
          && $organizationId === $event->organizationId
          && $invitationId === $event->invitationId
          && $email === $event->invitedEmail
          && $inviterUserId === $event->invitedByUserId
          && false === $event->resend,
      ));

    $invitationNotifier = new OrganizationInvitationNotifier(
      $notificationPort,
      'http://localhost:4200',
      new OrganizationInvitationTokenHasher(),
      EmailTranslatorTestFactory::create(),
    );

    $handler = new InviteOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
      invitationRepository: $invitationRepository,
      userRepository: $userRepository,
      invitationNotifier: $invitationNotifier,
      logger: $logger,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new InviteOrganizationMemberCommand(
      organizationId: $organizationId,
      email: $email,
      invitedByUserId: $inviterUserId,
      roleIds: [],
    ));

    self::assertInstanceOf(InviteOrganizationMemberResult::class, $result);
    self::assertSame($invitationId, $result->invitationId);
    self::assertSame('pending', $result->status);
  }

  #[Test]
  public function testInvokeDoesNotDispatchSentEventWhenQuotaIsExceeded(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441910';
    $invitationId = '550e8400-e29b-41d4-a716-446655441911';
    $roleId = '550e8400-e29b-41d4-a716-446655441912';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655441913';
    $email = 'member@example.com';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: $inviterUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $defaultRole = OrganizationRole::reconstitute(
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

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->willReturn($defaultRole);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([$defaultRole]);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())
      ->method('findByOrganizationAndUser');

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())
      ->method('findPendingByOrganizationAndEmail')
      ->willReturn(null);
    $invitationRepository->expects(self::never())
      ->method('save');

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findByEmail')
      ->willReturn(null);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())
      ->method('send');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OrganizationInvitationId::class)
      ->willReturn(new OrganizationInvitationId($invitationId));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())
      ->method('warning');

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())
      ->method('assertCanAdd')
      ->with($organizationId, OrganizationQuotaResource::MEMBERS)
      ->willThrowException(OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::MEMBERS->value, 1));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())
      ->method('dispatch');

    $invitationNotifier = new OrganizationInvitationNotifier(
      $notificationPort,
      'http://localhost:4200',
      new OrganizationInvitationTokenHasher(),
      EmailTranslatorTestFactory::create(),
    );

    $handler = new InviteOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
      invitationRepository: $invitationRepository,
      userRepository: $userRepository,
      invitationNotifier: $invitationNotifier,
      logger: $logger,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
      quota: $quota,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationQuotaExceededException::class);

    $handler->__invoke(new InviteOrganizationMemberCommand(
      organizationId: $organizationId,
      email: $email,
      invitedByUserId: $inviterUserId,
      roleIds: [],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationDoesNotExist(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn(null);

    $handler = $this->makeHandler(organizationRepository: $organizationRepository);

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new InviteOrganizationMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      email: 'member@example.com',
      invitedByUserId: self::INVITER_USER_ID,
      roleIds: [],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenEmailIsMalformed(): void
  {
    $handler = $this->makeHandler(organizationRepository: $this->organizationRepositoryStub());

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid email address.');

    $handler->__invoke(new InviteOrganizationMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      email: 'not-an-email',
      invitedByUserId: self::INVITER_USER_ID,
      roleIds: [],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenALivePendingInvitationAlreadyExists(): void
  {
    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())
      ->method('findPendingByOrganizationAndEmail')
      ->willReturn($this->pendingInvitation(new DateTimeImmutable('+3 days')));
    $invitationRepository->expects(self::never())->method('save');

    $handler = $this->makeHandler(
      organizationRepository: $this->organizationRepositoryStub(),
      invitationRepository: $invitationRepository,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('A pending invitation already exists for this email.');

    $handler->__invoke(new InviteOrganizationMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      email: 'member@example.com',
      invitedByUserId: self::INVITER_USER_ID,
      roleIds: [],
    ));
  }

  #[Test]
  public function testInvokeExpiresAStalePendingInvitationBeforeContinuing(): void
  {
    $stale = $this->pendingInvitation(new DateTimeImmutable('-1 day'));

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())
      ->method('findPendingByOrganizationAndEmail')
      ->willReturn($stale);
    $invitationRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (OrganizationInvitation $invitation): bool => 'expired' === $invitation->status()->value));

    // No default "member" role exists, so the use case stops right after the
    // stale invitation has been expired and persisted.
    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->willReturn(null);

    $handler = $this->makeHandler(
      organizationRepository: $this->organizationRepositoryStub(),
      roleRepository: $roleRepository,
      invitationRepository: $invitationRepository,
    );

    $this->expectException(OrganizationRoleNotFoundException::class);

    $handler->__invoke(new InviteOrganizationMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      email: 'member@example.com',
      invitedByUserId: self::INVITER_USER_ID,
      roleIds: [],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTheInviteeIsAlreadyAnActiveMember(): void
  {
    $userRepository = $this->createStub(UserRepositoryPort::class);
    $userRepository->method('findByEmail')->willReturn($this->existingUser());

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->willReturn(OrganizationMember::reconstitute(
        id: new OrganizationMemberId(self::MEMBER_ID),
        organizationId: new OrganizationId(self::ORGANIZATION_ID),
        userId: self::EXISTING_USER_ID,
        isActive: true,
        joinedAt: new DateTimeImmutable('-1 day'),
      ));

    $handler = $this->makeHandler(
      organizationRepository: $this->organizationRepositoryStub(),
      memberRepository: $memberRepository,
      userRepository: $userRepository,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('User is already an active member of this organization.');

    $handler->__invoke(new InviteOrganizationMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      email: 'member@example.com',
      invitedByUserId: self::INVITER_USER_ID,
      roleIds: [],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenARequestedRoleDoesNotBelongToTheOrganization(): void
  {
    $roleId = '550e8400-e29b-41d4-a716-446655441942';

    $userRepository = $this->createStub(UserRepositoryPort::class);
    $userRepository->method('findByEmail')->willReturn($this->existingUser());

    // A deactivated membership must not block a fresh invitation.
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationAndUser')->willReturn(OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORGANIZATION_ID),
      userId: self::EXISTING_USER_ID,
      isActive: false,
      joinedAt: new DateTimeImmutable('-1 day'),
    ));

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([]);

    $handler = $this->makeHandler(
      organizationRepository: $this->organizationRepositoryStub(),
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
      userRepository: $userRepository,
    );

    $this->expectException(OrganizationRoleNotFoundException::class);

    $handler->__invoke(new InviteOrganizationMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      email: 'member@example.com',
      invitedByUserId: self::INVITER_USER_ID,
      roleIds: [$roleId, $roleId],
    ));
  }

  private function organizationRepositoryStub(): OrganizationRepositoryPort
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn(Organization::reconstitute(
      id: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: self::INVITER_USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    ));

    return $organizationRepository;
  }

  private function pendingInvitation(DateTimeImmutable $expiresAt): OrganizationInvitation
  {
    return OrganizationInvitation::create(
      id: new OrganizationInvitationId(self::INVITATION_ID),
      organizationId: new OrganizationId(self::ORGANIZATION_ID),
      email: new Email('member@example.com'),
      tokenHash: 'token-hash',
      invitedByUserId: self::INVITER_USER_ID,
      expiresAt: $expiresAt,
    );
  }

  private function existingUser(): User
  {
    return User::register(
      id: new UserId(self::EXISTING_USER_ID),
      username: new Username('jdoe'),
      email: new Email('member@example.com'),
      password: HashedPassword::fromPlain('Str0ng!Passw0rd'),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: new TestEventIdProvider(),
    );
  }

  private function makeHandler(
    ?OrganizationRepositoryPort $organizationRepository = null,
    ?OrganizationRoleRepositoryPort $roleRepository = null,
    ?OrganizationMemberRepositoryPort $memberRepository = null,
    ?OrganizationInvitationRepositoryPort $invitationRepository = null,
    ?UserRepositoryPort $userRepository = null,
  ): InviteOrganizationMemberHandler {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $invitationNotifier = new OrganizationInvitationNotifier(
      $this->createStub(NotificationPort::class),
      'http://localhost:4200',
      new OrganizationInvitationTokenHasher(),
      EmailTranslatorTestFactory::create(),
    );

    return new InviteOrganizationMemberHandler(
      organizationRepository: $organizationRepository ?? $this->createStub(OrganizationRepositoryPort::class),
      roleRepository: $roleRepository ?? $this->createStub(OrganizationRoleRepositoryPort::class),
      memberRepository: $memberRepository ?? $this->createStub(OrganizationMemberRepositoryPort::class),
      invitationRepository: $invitationRepository ?? $this->createStub(OrganizationInvitationRepositoryPort::class),
      userRepository: $userRepository ?? $this->createStub(UserRepositoryPort::class),
      invitationNotifier: $invitationNotifier,
      logger: $this->createStub(LoggerPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
      transactionManager: $transactionManager,
      quota: $this->createStub(OrganizationQuotaPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );
  }
}
