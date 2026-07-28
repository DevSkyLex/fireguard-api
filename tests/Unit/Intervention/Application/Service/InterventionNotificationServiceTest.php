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

  private const OTHER_MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c15';

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
        && 'intervention-1' === $request->payload['interventionId']
        && self::ORGANIZATION_ID === $request->organizationId))
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

  #[Test]
  public function itDoesNotFailTheCommentWhenMentionDeliveryFails(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createStub(NotificationPort::class);
    $notifications->method('send')->willThrowException(new RuntimeException('Mercure unavailable'));

    new InterventionNotificationService($notifications, $members, $this->policy())
      ->mentioned('intervention-1', self::ORGANIZATION_ID, self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itDeliversAMentionInAppAndByEmail(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'intervention.comment_mention' === $request->type
        && [NotificationChannel::MERCURE, NotificationChannel::EMAIL] === $request->channels
        && self::USER_ID === $request->recipientUserId
        && self::ORGANIZATION_ID === $request->organizationId))
      ->willReturn($this->sent());

    new InterventionNotificationService($notifications, $members, $this->policy())
      ->mentioned('intervention-1', self::ORGANIZATION_ID, self::MEMBER_ID);
  }

  #[Test]
  public function itDropsTheEmailChannelForMentionsWhenEmailDeliveryIsDisabled(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => [NotificationChannel::MERCURE] === $request->channels))
      ->willReturn($this->sent());

    new InterventionNotificationService($notifications, $members, $this->policy(emailEnabled: false))
      ->mentioned('intervention-1', self::ORGANIZATION_ID, self::MEMBER_ID);
  }

  #[Test]
  public function itNeverNotifiesAMentionedMemberFromAnotherOrganization(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $members, $this->policy())
      ->mentioned('intervention-1', '018f0b68-6758-7a12-8a1d-3f0d97f63c99', self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itDoesNotNotifyChangesRequestedWhenThereIsNoResponsible(): void
  {
    $members = $this->createMock(OrganizationMemberRepositoryPort::class);
    $members->expects(self::never())->method('findById');
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $members, $this->policy())
      ->changesRequested('intervention-1', 'Annual inventory', null);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itNotifiesTheResponsibleWhenChangesAreRequested(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'intervention.changes_requested' === $request->type
        && [NotificationChannel::MERCURE] === $request->channels
        && self::USER_ID === $request->recipientUserId
        && 'intervention-9' === $request->payload['interventionId']
        && self::ORGANIZATION_ID === $request->organizationId))
      ->willReturn($this->sent());

    // The changes-requested event has no dedicated policy toggle: it falls
    // through the category map's default arm and is delivered even when both
    // intervention categories are switched off.
    new InterventionNotificationService($notifications, $members, $this->policy(interventionAssigned: false, interventionPublished: false))
      ->changesRequested('intervention-9', 'Annual inventory', self::MEMBER_ID);
  }

  #[Test]
  public function itPublishesToEachUniqueMemberExactlyOnce(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'intervention.published' === $request->type
        && [NotificationChannel::MERCURE] === $request->channels
        && 'intervention-1' === $request->payload['interventionId']))
      ->willReturn($this->sent());

    // The duplicate member id must collapse to a single notification.
    new InterventionNotificationService($notifications, $members, $this->policy())
      ->published('intervention-1', 'Annual inventory', [self::MEMBER_ID, self::MEMBER_ID, self::OTHER_MEMBER_ID]);
  }

  #[Test]
  public function itDoesNotPublishWhenThePublicationCategoryIsDisabled(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $members, $this->policy(interventionPublished: false))
      ->published('intervention-1', 'Annual inventory', [self::MEMBER_ID]);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itDoesNotNotifyWhenTheAssignedMemberIsUnknown(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn(null);
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $members, $this->policy())
      ->assigned('intervention-1', 'Annual inventory', self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itDoesNotNotifyAnInactiveMember(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member(isActive: false));
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $members, $this->policy())
      ->assigned('intervention-1', 'Annual inventory', self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itNeverNotifiesAMentionForAnUnknownMember(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn(null);
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $members, $this->policy())
      ->mentioned('intervention-1', self::ORGANIZATION_ID, self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itNeverNotifiesAMentionForAnInactiveMember(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member(isActive: false));
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $members, $this->policy())
      ->mentioned('intervention-1', self::ORGANIZATION_ID, self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itDoesNotDeliverAMentionWhenAllChannelsAreDisabled(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $members, $this->policy(inAppEnabled: false, emailEnabled: false))
      ->mentioned('intervention-1', self::ORGANIZATION_ID, self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  private function member(bool $isActive = true): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::MEMBER_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      self::USER_ID,
      $isActive,
      new DateTimeImmutable(),
    );
  }

  private function policy(bool $inAppEnabled = true, bool $interventionAssigned = true, bool $emailEnabled = true, bool $interventionPublished = true): OrganizationNotificationPolicyPort
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings(
      emailEnabled: $emailEnabled,
      inAppEnabled: $inAppEnabled,
      interventionPublished: $interventionPublished,
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
