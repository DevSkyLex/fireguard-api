<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\RemoveOrganizationMember;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Command\Organization\RemoveOrganizationMember\{RemoveOrganizationMemberCommand, RemoveOrganizationMemberHandler, RemoveOrganizationMemberResult};
use Organization\Domain\Event\Member\OrganizationMemberRemovedEvent;
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};

use function is_string;

#[CoversClass(RemoveOrganizationMemberHandler::class)]
final class RemoveOrganizationMemberHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655470001';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655470002';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655470003';

  private const string OTHER_ORG_ID = '550e8400-e29b-41d4-a716-446655470099';

  // #region Methods
  #[Test]
  public function testInvokeDeactivatesMemberWhenMemberBelongsToOrganization(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);
    $memberRepository->expects(self::once())->method('save')->with($member);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request): bool {
        return NotificationType::ORGANIZATION_MEMBER_REMOVED === $request->type
          && 'Organization access removed' === $request->subject
          && 'Your access to Fireguard Paris has been removed.' === $request->body
          && [NotificationChannel::MERCURE] === $request->channels
          && self::ORG_ID === ($request->payload['organizationId'] ?? null)
          && self::MEMBER_ID === ($request->payload['memberId'] ?? null)
          && 'Fireguard Paris' === ($request->payload['organizationName'] ?? null)
          && is_string($request->payload['removedAt'] ?? null)
          && self::USER_ID === $request->recipientUserId
          && null === $request->recipientEmail
          && self::ORG_ID === $request->organizationId;
      }))
      ->willReturn(new SentNotification(
        id: '550e8400-e29b-41d4-a716-446655449003',
        type: NotificationType::ORGANIZATION_MEMBER_REMOVED,
        subject: 'Organization access removed',
        body: 'Your access to Fireguard Paris has been removed.',
        channels: [NotificationChannel::MERCURE->value],
        payload: [
          'organizationId' => self::ORG_ID,
          'memberId' => self::MEMBER_ID,
          'organizationName' => 'Fireguard Paris',
          'removedAt' => '2024-01-01T00:00:00+00:00',
        ],
        channelDelivery: [NotificationChannel::MERCURE->value => true],
        createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        recipientUserId: self::USER_ID,
      ));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event): bool {
        return $event instanceof OrganizationMemberRemovedEvent
          && self::ORG_ID === $event->organizationId
          && self::MEMBER_ID === $event->memberId
          && self::USER_ID === $event->userId;
      }));

    $handler = new RemoveOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RemoveOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));

    self::assertInstanceOf(RemoveOrganizationMemberResult::class, $result);
    self::assertSame(self::MEMBER_ID, $result->memberId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertFalse($member->isActive());
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

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RemoveOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new RemoveOrganizationMemberCommand(
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
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn(null);
    $memberRepository->expects(self::never())->method('save');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RemoveOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new RemoveOrganizationMemberCommand(
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
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $memberFromAnotherOrg = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::OTHER_ORG_ID),
      userId: self::USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($memberFromAnotherOrg);
    $memberRepository->expects(self::never())->method('save');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RemoveOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new RemoveOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testInvokeReturnsResultWhenNotificationDispatchFails(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);
    $memberRepository->expects(self::once())->method('save')->with($member);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->willThrowException(new RuntimeException('Mercure hub unavailable.'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'Organization member removed notification dispatch failed.',
        [
          'organizationId' => self::ORG_ID,
          'memberId' => self::MEMBER_ID,
          'recipientUserId' => self::USER_ID,
          'error' => 'Mercure hub unavailable.',
        ],
      );

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event): bool {
        return $event instanceof OrganizationMemberRemovedEvent
          && self::ORG_ID === $event->organizationId
          && self::MEMBER_ID === $event->memberId
          && self::USER_ID === $event->userId;
      }));

    $handler = new RemoveOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RemoveOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));

    self::assertInstanceOf(RemoveOrganizationMemberResult::class, $result);
    self::assertSame(self::MEMBER_ID, $result->memberId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertFalse($member->isActive());
  }

  #[Test]
  public function testInvokeDoesNotNotifyWhenMemberAlreadyInactive(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::USER_ID,
      isActive: false,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);
    $memberRepository->expects(self::never())->method('save');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RemoveOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RemoveOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));

    self::assertInstanceOf(RemoveOrganizationMemberResult::class, $result);
    self::assertSame(self::MEMBER_ID, $result->memberId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertFalse($member->isActive());
  }
  // #endregion
}
