<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\UseCase\Command\Notification\DeliverRequestedNotification;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Exception\NotificationDeliveryFailedException;
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Application\UseCase\Command\Notification\DeliverRequestedNotification\{DeliverRequestedNotificationCommand, DeliverRequestedNotificationHandler};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\IdempotentConsumerPort;

#[CoversClass(DeliverRequestedNotificationHandler::class)]
final class DeliverRequestedNotificationHandlerTest extends TestCase
{
  #[Test]
  public function usesTheSameDurableRecipientIdentityAcrossRetries(): void
  {
    $requests = [];
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))->method('send')->willReturnCallback(function (SendNotificationRequest $request) use (&$requests): SentNotification {
      $requests[] = $request;

      return $this->sent(['mercure' => 'suppressed', 'email' => 'delivered']);
    });
    $consumer = $this->createMock(IdempotentConsumerPort::class);
    $consumer->expects(self::exactly(2))->method('consume')->with('persisted-event', 'notification.delivery:organization_plan_over_quota:owner', self::isCallable())
      ->willReturnCallback(static function (string $event, string $identity, callable $work): bool {
        $work();

        return true;
      });
    $handler = new DeliverRequestedNotificationHandler($notifications, $consumer);
    $command = new DeliverRequestedNotificationCommand('persisted-event', $this->request());
    $handler($command);
    $handler($command);
    self::assertSame($requests[0]->idempotencyKey, $requests[1]->idempotencyKey);
    self::assertSame('persisted-event:notification.delivery:organization_plan_over_quota:owner', $requests[0]->idempotencyKey);
    self::assertNull($requests[0]->recipientEmail);
    self::assertSame($command->request->payload, $requests[0]->payload);
  }

  #[Test]
  public function propagatesAChannelFailureBeforeTheConsumerReceiptCommits(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())->method('send')->willReturn($this->sent(['mercure' => 'delivered', 'email' => 'failed']));
    $consumer = $this->createMock(IdempotentConsumerPort::class);
    $consumer->expects(self::once())->method('consume')->willReturnCallback(static function (string $event, string $identity, callable $work): bool {
      $work();

      return true;
    });
    $this->expectException(NotificationDeliveryFailedException::class);
    new DeliverRequestedNotificationHandler($notifications, $consumer)(new DeliverRequestedNotificationCommand('persisted-event', $this->request()));
  }

  #[Test]
  public function skipsDeliveryAfterTheConsumerHasCommitted(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');
    $consumer = $this->createMock(IdempotentConsumerPort::class);
    $consumer->expects(self::once())->method('consume')->willReturn(false);
    new DeliverRequestedNotificationHandler($notifications, $consumer)(new DeliverRequestedNotificationCommand('persisted-event', $this->request()));
  }

  private function request(): SendNotificationRequest
  {
    return new SendNotificationRequest('organization_plan_over_quota', 'Plan changed', 'Immutable usage 60/50', [NotificationChannel::MERCURE, NotificationChannel::EMAIL], ['organizationName' => 'Original name', 'violations' => ['members' => ['usage' => 60, 'limit' => 50]]], recipientUserId: 'owner', organizationId: 'organization');
  }

  /**
   * @param array<string, string> $statuses terminal and failed channel outcomes
   */
  private function sent(array $statuses): SentNotification
  {
    return new SentNotification('notification', 'organization_plan_over_quota', 'Plan changed', 'Immutable usage 60/50', ['email', 'mercure'], [], ['email' => true, 'mercure' => true], new DateTimeImmutable(), 'owner', channelStatus: $statuses);
  }
}
