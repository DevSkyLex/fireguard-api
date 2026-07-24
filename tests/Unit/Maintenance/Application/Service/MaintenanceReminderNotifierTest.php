<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\Service;

use DateTimeImmutable;
use Maintenance\Application\Service\{MaintenanceReminderNotifier, MaintenanceReminderRecipientResolver};
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationNotificationPolicyPort};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationNotificationSettings};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_keys;
use function array_map;

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
  public function testRemindNeverThrowsWhenDeliveryFails(): void
  {
    $notifications = $this->createStub(NotificationPort::class);
    $notifications->method('send')->willThrowException(new RuntimeException('unavailable'));

    $notifier = new MaintenanceReminderNotifier($notifications, $this->policy(), $this->recipients(['user-1']));

    $notifier->remind(self::ORG_ID, self::EQUIP_ID, null, new DateTimeImmutable(), true);

    self::addToAssertionCount(1);
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
    $members = array_map(
      static fn (int $index, string $userId): OrganizationMember => OrganizationMember::reconstitute(
        OrganizationMemberId::fromString('550e8400-e29b-41d4-a716-4466554400' . (20 + $index)),
        OrganizationId::fromString(self::ORG_ID),
        $userId,
        true,
        new DateTimeImmutable(),
      ),
      array_keys($userIds),
      $userIds,
    );

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationId')->willReturn($members);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(['organization.maintenance.manage']);

    return new MaintenanceReminderRecipientResolver($memberRepository, $authorization);
  }

  private function policy(bool $inspectionDue = true, bool $inAppEnabled = true, bool $emailEnabled = true): OrganizationNotificationPolicyPort
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings(
      emailEnabled: $emailEnabled,
      inAppEnabled: $inAppEnabled,
      inspectionDue: $inspectionDue,
    ));

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
