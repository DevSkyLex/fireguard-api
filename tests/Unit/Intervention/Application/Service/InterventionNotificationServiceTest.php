<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Service\InterventionNotificationService;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\OrganizationNotificationPolicyPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationNotificationSettings};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InterventionNotificationServiceTest extends TestCase
{
  private const MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  private const USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c13';

  #[Test]
  public function itSendsAnInAppAssignmentNotification(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'intervention.assigned' === $request->type
        && [NotificationChannel::MERCURE] === $request->channels
        && self::USER_ID === $request->recipientUserId
        && 'intervention-1' === $request->payload['interventionId']))
      ->willReturn($this->sent());

    new InterventionNotificationService($notifications, $members, $this->policy())
      ->assigned('intervention-1', 'Annual inventory', self::MEMBER_ID);
  }

  #[Test]
  public function itDoesNotNotifyWhenTheAssignmentCategoryIsDisabled(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $members, $this->policy(interventionAssigned: false))
      ->assigned('intervention-1', 'Annual inventory', self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itDoesNotNotifyWhenInAppDeliveryIsDisabled(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $members, $this->policy(inAppEnabled: false))
      ->assigned('intervention-1', 'Annual inventory', self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itDoesNotFailTheInterventionWhenNotificationDeliveryFails(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createStub(NotificationPort::class);
    $notifications->method('send')->willThrowException(new RuntimeException('Mercure unavailable'));

    new InterventionNotificationService($notifications, $members, $this->policy())
      ->assigned('intervention-1', 'Annual inventory', self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  private function member(): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::MEMBER_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      self::USER_ID,
      true,
      new DateTimeImmutable(),
    );
  }

  private function policy(bool $inAppEnabled = true, bool $interventionAssigned = true): OrganizationNotificationPolicyPort
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings(
      inAppEnabled: $inAppEnabled,
      interventionAssigned: $interventionAssigned,
    ));

    return $policy;
  }

  private function sent(): SentNotification
  {
    return new SentNotification(
      '018f0b68-6758-7a12-8a1d-3f0d97f63c14',
      'intervention.assigned',
      'Intervention assigned',
      'Assigned',
      [NotificationChannel::MERCURE->value],
      [],
      [NotificationChannel::MERCURE->value => true],
      new DateTimeImmutable(),
      self::USER_ID,
      null,
    );
  }
}
