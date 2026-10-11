<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\Service;

use DateTimeImmutable;
use Maintenance\Application\Service\{MaintenanceReminderNotifier, MaintenanceReminderRecipientResolver};
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Contract\Notification\OrganizationNotificationSettings;
use Organization\Application\Port\Inbound\{OrganizationMemberNotificationDirectoryPort, OrganizationNotificationPolicyPort};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test MaintenanceReminderNotifierTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceReminderNotifier::class)]
final class MaintenanceReminderNotifierTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testRemindSendsAnOverdueNotificationToEveryAdministrator(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'maintenance.inspection_overdue' === $request->type
        && [NotificationChannel::MERCURE, NotificationChannel::EMAIL] === $request->channels
        && self::EQUIP_ID === $request->payload['equipmentId']
        && self::ORG_ID === $request->organizationId))
      ->willReturn($this->sent());

    $notifier = new MaintenanceReminderNotifier($notifications, $this->policy(), $this->recipients(['user-1', 'user-2']));

    $notifier->remind(self::ORG_ID, self::EQUIP_ID, 'facility-1', new DateTimeImmutable('2026-02-01'), true);
  }

  #[Test]
  public function testRemindSendsADueSoonNotificationType(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'maintenance.inspection_due' === $request->type
        && self::ORG_ID === $request->organizationId))
      ->willReturn($this->sent());

    $notifier = new MaintenanceReminderNotifier($notifications, $this->policy(), $this->recipients(['user-1']));

    $notifier->remind(self::ORG_ID, self::EQUIP_ID, null, new DateTimeImmutable('2026-02-01'), false);
  }

  #[Test]
  public function testRemindDoesNothingWhenInspectionDueCategoryIsDisabled(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $notifier = new MaintenanceReminderNotifier(
      $notifications,
      $this->policy(inspectionDue: false),
      $this->recipients(['user-1']),
    );

    $notifier->remind(self::ORG_ID, self::EQUIP_ID, null, new DateTimeImmutable(), true);
  }

  #[Test]
  public function testRemindDropsTheEmailChannelWhenEmailDeliveryIsDisabled(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => [NotificationChannel::MERCURE] === $request->channels))
      ->willReturn($this->sent());

    $notifier = new MaintenanceReminderNotifier(
      $notifications,
      $this->policy(emailEnabled: false),
      $this->recipients(['user-1']),
    );

    $notifier->remind(self::ORG_ID, self::EQUIP_ID, null, new DateTimeImmutable(), true);
  }

  #[Test]
  public function testRemindDoesNothingWhenNoChannelRemainsEnabled(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $notifier = new MaintenanceReminderNotifier(
      $notifications,
      $this->policy(emailEnabled: false, inAppEnabled: false),
      $this->recipients(['user-1']),
    );

    $notifier->remind(self::ORG_ID, self::EQUIP_ID, null, new DateTimeImmutable(), true);
  }

  #[Test]
  public function testRemindPropagatesRetryableDeliveryFailure(): void
  {
    $notifications = $this->createStub(NotificationPort::class);
    $notifications->method('send')->willThrowException(new RuntimeException('unavailable'));

    $notifier = new MaintenanceReminderNotifier($notifications, $this->policy(), $this->recipients(['user-1']));

    $this->expectException(RuntimeException::class);
    $notifier->remind(self::ORG_ID, self::EQUIP_ID, null, new DateTimeImmutable(), true);
  }

  #[Test]
  public function testRemindPropagatesUnreadablePolicyForRetry(): void
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willThrowException(new RuntimeException('policy store down'));

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $notifier = new MaintenanceReminderNotifier($notifications, $policy, $this->recipients(['user-1']));

    $this->expectException(RuntimeException::class);
    $notifier->remind(self::ORG_ID, self::EQUIP_ID, null, new DateTimeImmutable(), true);
  }

  /**
   * Builds a real resolver (the concrete class is final and cannot be
   * doubled) backed by stubbed ports, so it deterministically resolves to
   * the given user ids as active organization administrators.
   *
   * @param list<string> $userIds
   */
  private function recipients(array $userIds): MaintenanceReminderRecipientResolver
  {
    $directory = $this->createStub(OrganizationMemberNotificationDirectoryPort::class);
    $directory->method('administratorUserIds')->willReturn($userIds);

    return new MaintenanceReminderRecipientResolver($directory);
  }

  private function policy(bool $inspectionDue = true, bool $inAppEnabled = true, bool $emailEnabled = true): OrganizationNotificationPolicyPort
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(OrganizationNotificationSettings::fromArray([
      'email_enabled' => $emailEnabled,
      'in_app_enabled' => $inAppEnabled,
      'inspection_due' => $inspectionDue,
    ]));

    return $policy;
  }

  private function sent(): SentNotification
  {
    return new SentNotification(
      '550e8400-e29b-41d4-a716-446655440099',
      'maintenance.inspection_overdue',
      'Inspection overdue',
      'body',
      [NotificationChannel::MERCURE->value],
      [],
      [NotificationChannel::MERCURE->value => true],
      new DateTimeImmutable(),
      'user-1',
      null,
    );
  }
}
