<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Domain\Model\Notification;

use DateTimeImmutable;
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test NotificationTest.
 *
 * @category Domain Model Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Notification::class)]
final class NotificationTest extends TestCase
{
  private const string NOTIFICATION_ID = '550e8400-e29b-41d4-a716-446655440000';

  // #region Methods
  #[Test]
  public function testMarkAsReadStampsTheGivenInstant(): void
  {
    $notification = $this->makeNotification();
    $readAt = new DateTimeImmutable('2026-02-06T09:00:00+00:00');

    $notification->markAsRead($readAt);

    self::assertTrue($notification->isRead());
    self::assertSame($readAt, $notification->readAt());
    self::assertSame($readAt, $notification->updatedAt());
  }

  #[Test]
  public function testMarkAsReadIsIdempotent(): void
  {
    $notification = $this->makeNotification();
    $firstReadAt = new DateTimeImmutable('2026-02-06T09:00:00+00:00');
    $notification->markAsRead($firstReadAt);

    $notification->markAsRead(new DateTimeImmutable('2026-02-07T09:00:00+00:00'));

    self::assertTrue($notification->isRead());
    self::assertSame($firstReadAt, $notification->readAt());
    self::assertSame($firstReadAt, $notification->updatedAt());
  }

  private function makeNotification(): Notification
  {
    return Notification::create(
      id: NotificationId::fromString(self::NOTIFICATION_ID),
      type: 'organization.invitation',
      subject: 'Invitation',
      body: 'You were invited.',
      channels: ['email'],
    );
  }
  // #endregion
}
