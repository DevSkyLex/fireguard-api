<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\UseCase\Query\Notification\ListUserNotifications;

use DateTimeImmutable;
use Notification\Application\Port\Outbound\NotificationRepositoryPort;
use Notification\Application\UseCase\Query\Notification\GetUserNotification\GetUserNotificationResult;
use Notification\Application\UseCase\Query\Notification\ListUserNotifications\{ListUserNotificationsHandler, ListUserNotificationsQuery, ListUserNotificationsResult};
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\{NotificationId, NotificationType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\Pagination;

#[CoversClass(ListUserNotificationsHandler::class)]
final class ListUserNotificationsHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeAppliesReadVisibilityMaskForLowValueCategories(): void
  {
    $notification = Notification::create(
      id: NotificationId::fromString('550e8400-e29b-41d4-a716-446655442300'),
      type: NotificationType::FACILITY_ARCHIVED,
      subject: 'Facility archived',
      body: 'Facility HQ has been archived.',
      channels: ['mercure'],
      recipientUserId: '550e8400-e29b-41d4-a716-446655442301',
    );

    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->with(
        '550e8400-e29b-41d4-a716-446655442301',
        false,
        100,
        0,
        null,
        null,
        null,
        self::callback(static function (?DateTimeImmutable $hideReadBefore): bool {
          if (!$hideReadBefore instanceof DateTimeImmutable) {
            return false;
          }

          $ageInSeconds = new DateTimeImmutable()->getTimestamp() - $hideReadBefore->getTimestamp();

          return $ageInSeconds >= 29 * 24 * 60 * 60
            && $ageInSeconds <= 31 * 24 * 60 * 60;
        }),
        [
          NotificationType::CATEGORY_USER,
          NotificationType::CATEGORY_FACILITY,
          NotificationType::CATEGORY_EQUIPMENT,
        ],
      )
      ->willReturn([$notification]);
    $repository->expects(self::once())
      ->method('countByUserId')
      ->willReturn(1);

    $handler = new ListUserNotificationsHandler(
      notificationRepository: $repository,
    );

    $result = $handler->__invoke(new ListUserNotificationsQuery(
      userId: '550e8400-e29b-41d4-a716-446655442301',
      onlyUnread: false,
      pagination: new Pagination(offset: 0, limit: 999),
    ));

    self::assertInstanceOf(ListUserNotificationsResult::class, $result);
    self::assertCount(1, $result->notifications);
    self::assertSame(1, $result->total);
    self::assertInstanceOf(GetUserNotificationResult::class, $result->notifications[0]);
    self::assertSame(NotificationType::FACILITY_ARCHIVED, $result->notifications[0]->type);
  }

  #[Test]
  public function testInvokeSkipsReadVisibilityMaskWhenOnlyUnreadIsRequested(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->with(
        '550e8400-e29b-41d4-a716-446655442302',
        true,
        5,
        0,
        null,
        null,
        null,
        null,
        [],
      )
      ->willReturn([]);
    $repository->expects(self::once())
      ->method('countByUserId')
      ->willReturn(0);

    $handler = new ListUserNotificationsHandler(
      notificationRepository: $repository,
    );

    $result = $handler->__invoke(new ListUserNotificationsQuery(
      userId: '550e8400-e29b-41d4-a716-446655442302',
      onlyUnread: true,
      pagination: new Pagination(offset: 0, limit: 5),
    ));

    self::assertInstanceOf(ListUserNotificationsResult::class, $result);
    self::assertSame([], $result->notifications);
    self::assertSame(0, $result->total);
  }

  #[Test]
  public function testInvokeAppliesOrganizationFilterAndOffset(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->with(
        '550e8400-e29b-41d4-a716-446655442303',
        false,
        10,
        20,
        null,
        null,
        '550e8400-e29b-41d4-a716-446655442399',
        self::isInstanceOf(DateTimeImmutable::class),
        self::isArray(),
      )
      ->willReturn([]);
    $repository->expects(self::once())
      ->method('countByUserId')
      ->with(
        '550e8400-e29b-41d4-a716-446655442303',
        false,
        null,
        null,
        '550e8400-e29b-41d4-a716-446655442399',
        self::isInstanceOf(DateTimeImmutable::class),
        self::isArray(),
      )
      ->willReturn(37);

    $handler = new ListUserNotificationsHandler(
      notificationRepository: $repository,
    );

    $result = $handler->__invoke(new ListUserNotificationsQuery(
      userId: '550e8400-e29b-41d4-a716-446655442303',
      onlyUnread: false,
      pagination: new Pagination(offset: 20, limit: 10),
      organizationId: '550e8400-e29b-41d4-a716-446655442399',
    ));

    self::assertSame(37, $result->total);
    self::assertSame(10, $result->limit);
    self::assertSame(20, $result->offset);
  }
}
