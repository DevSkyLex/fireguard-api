<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\Contract\Notification;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SentNotification};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test SentNotificationTest.
 *
 * @category Contract Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SentNotification::class)]
final class SentNotificationTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testIsDeliveredReportsTheChannelDeliveryFlag(): void
  {
    $notification = $this->makeSentNotification([
      'email' => true,
      'mercure' => false,
    ]);

    self::assertTrue($notification->isDelivered(NotificationChannel::EMAIL));
    self::assertFalse($notification->isDelivered(NotificationChannel::MERCURE));
  }

  #[Test]
  public function testIsDeliveredFallsBackToFalseForAnUnreportedChannel(): void
  {
    $notification = $this->makeSentNotification([]);

    self::assertFalse($notification->isDelivered(NotificationChannel::EMAIL));
    self::assertFalse($notification->isDelivered(NotificationChannel::MERCURE));
  }

  #[Test]
  public function testExposesItsConstructorPayload(): void
  {
    $notification = $this->makeSentNotification(['email' => true]);

    self::assertSame('550e8400-e29b-41d4-a716-446655440000', $notification->id);
    self::assertSame('organization.invitation', $notification->type);
    self::assertSame('Invitation', $notification->subject);
    self::assertSame('You were invited.', $notification->body);
    self::assertSame(['email'], $notification->channels);
    self::assertSame(['organizationName' => 'Fireguard HQ'], $notification->payload);
    self::assertSame('user-1', $notification->recipientUserId);
    self::assertSame('member@example.com', $notification->recipientEmail);
    self::assertSame('organization-1', $notification->organizationId);
  }

  /**
   * @param array<string, bool> $channelDelivery the delivery status per channel
   */
  private function makeSentNotification(array $channelDelivery): SentNotification
  {
    return new SentNotification(
      id: '550e8400-e29b-41d4-a716-446655440000',
      type: 'organization.invitation',
      subject: 'Invitation',
      body: 'You were invited.',
      channels: ['email'],
      payload: ['organizationName' => 'Fireguard HQ'],
      channelDelivery: $channelDelivery,
      createdAt: new DateTimeImmutable('2026-02-05T10:00:00+00:00'),
      recipientUserId: 'user-1',
      recipientEmail: 'member@example.com',
      organizationId: 'organization-1',
    );
  }
  // #endregion
}
