<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Infrastructure\Adapter\Inbox;

use DateTimeImmutable;
use Notification\Application\Port\Outbound\NotificationRepositoryPort;
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use Notification\Infrastructure\Adapter\Inbox\NotificationInboxSourceProviderAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationInboxSourceProviderAdapter::class)]
final class NotificationInboxSourceProviderAdapterTest extends TestCase
{
  #[Test]
  public function testSourceKeyReturnsTheNotificationConstant(): void
  {
    $adapter = new NotificationInboxSourceProviderAdapter($this->createStub(NotificationRepositoryPort::class));

    self::assertSame('notification', $adapter->sourceKey());
    self::assertSame(NotificationInboxSourceProviderAdapter::SOURCE_KEY, $adapter->sourceKey());
  }

  #[Test]
  public function testFetchForwardsTheCursorOrganizationAndLimitToTheRepository(): void
  {
    $before = new DateTimeImmutable('2026-07-18T09:00:00+00:00');

    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->with(
        'user-1',
        false,
        15,
        0,
        null,
        null,
        'org-1',
        null,
        [],
        $before,
      )
      ->willReturn([]);

    $adapter = new NotificationInboxSourceProviderAdapter($repository);

    $adapter->fetch('user-1', 'org-1', $before, 15);
  }

  #[Test]
  public function testFetchMapsEachNotificationToAnInboxItem(): void
  {
    $notification = Notification::reconstitute(
      id: NotificationId::fromString('550e8400-e29b-41d4-a716-446655442500'),
      type: 'organization.invitation',
      subject: 'You were invited',
      body: 'Join the Fireguard HQ organization.',
      channels: ['mercure'],
      payload: [],
      createdAt: new DateTimeImmutable('2026-07-18T09:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-07-18T09:00:00+00:00'),
      recipientUserId: 'user-1',
      isRead: true,
      readAt: new DateTimeImmutable('2026-07-18T09:05:00+00:00'),
      organizationId: 'org-1',
    );

    $repository = $this->createStub(NotificationRepositoryPort::class);
    $repository->method('findByUserId')->willReturn([$notification]);

    $adapter = new NotificationInboxSourceProviderAdapter($repository);

    $items = $adapter->fetch('user-1', 'org-1', null, 20);

    self::assertCount(1, $items);
    $item = $items[0];
    self::assertSame('notification', $item->sourceKey);
    self::assertSame('550e8400-e29b-41d4-a716-446655442500', $item->id);
    self::assertSame('notification', $item->kind);
    self::assertSame('You were invited', $item->title);
    self::assertSame('Join the Fireguard HQ organization.', $item->snippet);
    self::assertEquals(new DateTimeImmutable('2026-07-18T09:00:00+00:00'), $item->occurredAt);
    self::assertTrue($item->isRead);
    self::assertSame('org-1', $item->organizationId);
    self::assertSame('notification', $item->targetType);
    self::assertSame('550e8400-e29b-41d4-a716-446655442500', $item->targetId);
  }
}
