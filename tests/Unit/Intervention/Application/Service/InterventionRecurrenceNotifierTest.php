<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Service\{InterventionRecurrenceNotifier, InterventionRecurrenceRecipientResolver};
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationNotificationPolicyPort};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationNotificationSettings};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
    $member = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::RESPONSIBLE_ID),
      OrganizationId::fromString(self::ORG_ID),
      'user-responsible',
      true,
      new DateTimeImmutable(),
    );

    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
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
      $this->createStub(OrganizationMemberRepositoryPort::class),
      $this->recipients(['user-a', 'user-b']),
    );

    $notifier->notifyMaterializationFailed(self::ORG_ID, 'recurrence-9', 'template-7', null, 'boom');
  }

  #[Test]
  public function testFallsBackToAdministratorsWhenResponsibleMemberIsInactive(): void
  {
    $inactive = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::RESPONSIBLE_ID),
      OrganizationId::fromString(self::ORG_ID),
      'user-inactive',
      false,
      new DateTimeImmutable(),
    );

    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
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
      $this->createStub(OrganizationMemberRepositoryPort::class),
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
      $this->createStub(OrganizationMemberRepositoryPort::class),
      $this->recipients(['user-admin']),
    );

    $notifier->notifyMaterializationFailed(self::ORG_ID, 'recurrence-9', 'template-7', null, 'boom');
  }

  #[Test]
  public function testNeverThrowsWhenDeliveryFails(): void
  {
    $notifications = $this->createStub(NotificationPort::class);
    $notifications->method('send')->willThrowException(new RuntimeException('unavailable'));

    $notifier = new InterventionRecurrenceNotifier(
      $notifications,
      $this->policy(),
      $this->createStub(OrganizationMemberRepositoryPort::class),
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
    $members = [];
    $offset = 40;
    foreach ($userIds as $userId) {
      $members[] = OrganizationMember::reconstitute(
        OrganizationMemberId::fromString('550e8400-e29b-41d4-a716-4466554400' . $offset),
        OrganizationId::fromString(self::ORG_ID),
        $userId,
        true,
        new DateTimeImmutable(),
      );
      ++$offset;
    }

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationId')->willReturn($members);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(['organization.interventions.plan']);

    return new InterventionRecurrenceRecipientResolver($memberRepository, $authorization);
  }

  private function policy(bool $inAppEnabled = true, bool $emailEnabled = true): OrganizationNotificationPolicyPort
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings(
      emailEnabled: $emailEnabled,
      inAppEnabled: $inAppEnabled,
    ));

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
