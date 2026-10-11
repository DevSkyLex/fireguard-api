<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Exception\InterventionNotificationDeliveryException;
use Intervention\Application\Service\{InterventionRecurrenceNotifier, InterventionRecurrenceRecipientResolver};
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Contract\Member\OrganizationMemberView;
use Organization\Application\Contract\Notification\OrganizationNotificationSettings;
use Organization\Application\Port\Inbound\{OrganizationMemberDirectoryPort, OrganizationNotificationPolicyPort};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\DurableEventContextPort;

use function count;

/**
 * Test InterventionRecurrenceNotifier.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionRecurrenceNotifier::class)]
final class InterventionRecurrenceNotifierTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string RESPONSIBLE_ID = '550e8400-e29b-41d4-a716-446655440005';

  #[Test]
  public function testNotifiesTheResponsibleMemberWhenStillActiveInTheOrganization(): void
  {
    $member = new OrganizationMemberView(self::RESPONSIBLE_ID, self::ORG_ID, 'user-responsible', true);

    $members = $this->createStub(OrganizationMemberDirectoryPort::class);
    $members->method('findById')->willReturn($member);

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'intervention.recurrence_failed' === $request->type
        && [NotificationChannel::MERCURE, NotificationChannel::EMAIL] === $request->channels
        && 'recurrence-9' === $request->payload['recurrenceId']
        && 'template-7' === $request->payload['templateId']
        && 'boom' === $request->payload['reason']
        && 'user-responsible' === $request->recipientUserId
        && self::ORG_ID === $request->organizationId))
      ->willReturn($this->sent());

    $notifier = new InterventionRecurrenceNotifier(
      $notifications,
      $this->policy(),
      $members,
      $this->recipients(['user-admin']),
    );

    $notifier->notifyMaterializationFailed(self::ORG_ID, 'recurrence-9', 'template-7', self::RESPONSIBLE_ID, 'boom');
  }

  #[Test]
  public function testFallsBackToAdministratorsWhenNoResponsibleMemberIsSet(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))
      ->method('send')
      ->willReturn($this->sent());

    $notifier = new InterventionRecurrenceNotifier(
      $notifications,
      $this->policy(),
      $this->createStub(OrganizationMemberDirectoryPort::class),
      $this->recipients(['user-a', 'user-b']),
    );

    $notifier->notifyMaterializationFailed(self::ORG_ID, 'recurrence-9', 'template-7', null, 'boom');
  }

  #[Test]
  public function testFallsBackToAdministratorsWhenResponsibleMemberIsInactive(): void
  {
    $inactive = new OrganizationMemberView(self::RESPONSIBLE_ID, self::ORG_ID, 'user-inactive', false);

    $members = $this->createStub(OrganizationMemberDirectoryPort::class);
    $members->method('findById')->willReturn($inactive);

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'user-admin' === $request->recipientUserId))
      ->willReturn($this->sent());

    $notifier = new InterventionRecurrenceNotifier(
      $notifications,
      $this->policy(),
      $members,
      $this->recipients(['user-admin']),
    );

    $notifier->notifyMaterializationFailed(self::ORG_ID, 'recurrence-9', 'template-7', self::RESPONSIBLE_ID, 'boom');
  }

  #[Test]
  public function testDropsTheEmailChannelWhenEmailDeliveryIsDisabled(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => [NotificationChannel::MERCURE] === $request->channels))
      ->willReturn($this->sent());

    $notifier = new InterventionRecurrenceNotifier(
      $notifications,
      $this->policy(emailEnabled: false),
      $this->createStub(OrganizationMemberDirectoryPort::class),
      $this->recipients(['user-admin']),
    );

    $notifier->notifyMaterializationFailed(self::ORG_ID, 'recurrence-9', 'template-7', null, 'boom');
  }

  #[Test]
  public function testSendsNothingWhenNoChannelIsEnabled(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $notifier = new InterventionRecurrenceNotifier(
      $notifications,
      $this->policy(emailEnabled: false, inAppEnabled: false),
      $this->createStub(OrganizationMemberDirectoryPort::class),
      $this->recipients(['user-admin']),
    );

    $notifier->notifyMaterializationFailed(self::ORG_ID, 'recurrence-9', 'template-7', null, 'boom');
  }

  #[Test]
  public function testNeverThrowsWhenDeliveryFails(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))->method('send')->willThrowException(new RuntimeException('unavailable'));

    $notifier = new InterventionRecurrenceNotifier(
      $notifications,
      $this->policy(),
      $this->createStub(OrganizationMemberDirectoryPort::class),
      $this->recipients(['user-admin-a', 'user-admin-b']),
    );

    $notifier->notifyMaterializationFailed(self::ORG_ID, 'recurrence-9', 'template-7', null, 'boom');

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testDurableChannelFailureRetriesWithStableRecipientIdentityAndAcceptsSuppression(): void
  {
    $requests = [];
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))->method('send')->willReturnCallback(static function (SendNotificationRequest $request) use (&$requests): SentNotification {
      $requests[] = $request;

      return new SentNotification('notification', $request->type, $request->subject, $request->body, ['mercure'], $request->payload, ['mercure' => false], new DateTimeImmutable(), channelStatus: ['mercure' => 1 === count($requests) ? 'failed' : 'suppressed']);
    });
    $eventContext = $this->createStub(DurableEventContextPort::class);
    $eventContext->method('eventId')->willReturn('recurrence-outcome');
    $notifier = new InterventionRecurrenceNotifier($notifications, $this->policy(), $this->createStub(OrganizationMemberDirectoryPort::class), $this->recipients(['user-admin']), $eventContext);

    try {
      $notifier->notifyMaterializationFailed(self::ORG_ID, 'recurrence-9', 'template-7', null, 'boom');
      self::fail('A failed durable channel must propagate for retry.');
    } catch (InterventionNotificationDeliveryException $failure) {
      self::assertSame('Recurrence notification has a failed channel; durable delivery will retry.', $failure->getMessage());
    }
    $notifier->notifyMaterializationFailed(self::ORG_ID, 'recurrence-9', 'template-7', null, 'boom');

    self::assertCount(2, $requests);
    self::assertSame('recurrence-outcome:intervention.recurrence_failed:user-admin', $requests[0]->idempotencyKey);
    self::assertSame($requests[0]->idempotencyKey, $requests[1]->idempotencyKey);
    self::assertSame($requests[0]->payload, $requests[1]->payload);
  }

  #[Test]
  public function testDurableDeliveryPreservesTheOriginalPortException(): void
  {
    $failure = new RuntimeException('delivery provider unavailable');
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())->method('send')->willThrowException($failure);
    $eventContext = $this->createStub(DurableEventContextPort::class);
    $eventContext->method('eventId')->willReturn('recurrence-outcome');
    $notifier = new InterventionRecurrenceNotifier($notifications, $this->policy(), $this->createStub(OrganizationMemberDirectoryPort::class), $this->recipients(['user-admin']), $eventContext);

    try {
      $notifier->notifyMaterializationFailed(self::ORG_ID, 'recurrence-9', 'template-7', null, 'boom');
      self::fail('A durable delivery exception must propagate unchanged.');
    } catch (RuntimeException $actual) {
      self::assertSame($failure, $actual);
    }
  }

  #[Test]
  public function testNeverThrowsWhenTheNotificationPolicyItselfFails(): void
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willThrowException(new RuntimeException('settings unavailable'));

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $notifier = new InterventionRecurrenceNotifier(
      $notifications,
      $policy,
      $this->createStub(OrganizationMemberDirectoryPort::class),
      $this->recipients(['user-admin']),
    );

    $notifier->notifyMaterializationFailed(self::ORG_ID, 'recurrence-9', 'template-7', null, 'boom');

    self::addToAssertionCount(1);
  }

  /**
   * Builds a real recipient resolver (the concrete class is final and cannot
   * be doubled) backed by stubbed ports, so it deterministically resolves to
   * the given user ids as active organization administrators.
   *
   * @param list<string> $userIds the administrator user identifiers to resolve
   */
  private function recipients(array $userIds): InterventionRecurrenceRecipientResolver
  {
    $directory = $this->createStub(\Organization\Application\Port\Inbound\OrganizationMemberNotificationDirectoryPort::class);
    $directory->method('administratorUserIds')->willReturn($userIds);

    return new InterventionRecurrenceRecipientResolver($directory);
  }

  private function policy(bool $inAppEnabled = true, bool $emailEnabled = true): OrganizationNotificationPolicyPort
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(OrganizationNotificationSettings::fromArray([
      'email_enabled' => $emailEnabled,
      'in_app_enabled' => $inAppEnabled,
    ]));

    return $policy;
  }

  private function sent(): SentNotification
  {
    return new SentNotification(
      '550e8400-e29b-41d4-a716-446655440099',
      'intervention.recurrence_failed',
      'Recurring intervention could not be created',
      'body',
      [NotificationChannel::MERCURE->value],
      [],
      [NotificationChannel::MERCURE->value => true],
      new DateTimeImmutable(),
      'user-admin',
      null,
    );
  }
}
