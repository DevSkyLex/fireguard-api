<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Service\{InterventionNotificationService, InterventionReviewerRecipientResolver};
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationNotificationPolicyPort};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationNotificationSettings};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_keys;
use function sprintf;

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

    new InterventionNotificationService($notifications, $members, $this->policy(), $this->reviewers())
      ->assigned('intervention-1', 'Annual inventory', self::MEMBER_ID);
  }

  #[Test]
  public function itDoesNotNotifyWhenTheAssignmentCategoryIsDisabled(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $members, $this->policy(interventionAssigned: false), $this->reviewers())
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

    new InterventionNotificationService($notifications, $members, $this->policy(inAppEnabled: false), $this->reviewers())
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

    new InterventionNotificationService($notifications, $members, $this->policy(), $this->reviewers())
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

    new InterventionNotificationService($notifications, $members, $this->policy(), $this->reviewers())
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

    new InterventionNotificationService($notifications, $members, $this->policy(), $this->reviewers())
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

    new InterventionNotificationService($notifications, $members, $this->policy(emailEnabled: false), $this->reviewers())
      ->mentioned('intervention-1', self::ORGANIZATION_ID, self::MEMBER_ID);
  }

  #[Test]
  public function itNeverNotifiesAMentionedMemberFromAnotherOrganization(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $members, $this->policy(), $this->reviewers())
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

    new InterventionNotificationService($notifications, $members, $this->policy(), $this->reviewers())
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
    new InterventionNotificationService($notifications, $members, $this->policy(interventionAssigned: false, interventionPublished: false), $this->reviewers())
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
    new InterventionNotificationService($notifications, $members, $this->policy(), $this->reviewers())
      ->published('intervention-1', 'Annual inventory', [self::MEMBER_ID, self::MEMBER_ID, self::OTHER_MEMBER_ID]);
  }

  #[Test]
  public function itDoesNotPublishWhenThePublicationCategoryIsDisabled(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member());
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $members, $this->policy(interventionPublished: false), $this->reviewers())
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

    new InterventionNotificationService($notifications, $members, $this->policy(), $this->reviewers())
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

    new InterventionNotificationService($notifications, $members, $this->policy(), $this->reviewers())
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

    new InterventionNotificationService($notifications, $members, $this->policy(), $this->reviewers())
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

    new InterventionNotificationService($notifications, $members, $this->policy(), $this->reviewers())
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

    new InterventionNotificationService($notifications, $members, $this->policy(inAppEnabled: false, emailEnabled: false), $this->reviewers())
      ->mentioned('intervention-1', self::ORGANIZATION_ID, self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itNotifiesEveryReviewerInAppAndByEmailOnSubmission(): void
  {
    $recipients = [];
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request) use (&$recipients): bool {
        $recipients[] = $request->recipientUserId;

        return 'intervention.submitted' === $request->type
          && [NotificationChannel::MERCURE, NotificationChannel::EMAIL] === $request->channels
          && 'intervention-7' === $request->payload['interventionId']
          && self::ORGANIZATION_ID === $request->organizationId;
      }))
      ->willReturn($this->sent());

    new InterventionNotificationService($notifications, $this->createStub(OrganizationMemberRepositoryPort::class), $this->policy(), $this->reviewers(
      ['user-reviewer-1' => ['organization.interventions.review'], 'user-reviewer-2' => ['organization.*']],
    ))->submitted('intervention-7', 'Annual inventory', self::ORGANIZATION_ID, 'user-submitter');

    self::assertSame(['user-reviewer-1', 'user-reviewer-2'], $recipients);
  }

  #[Test]
  public function itExcludesTheSubmittingUserFromSubmissionRecipients(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'user-reviewer-2' === $request->recipientUserId))
      ->willReturn($this->sent());

    new InterventionNotificationService($notifications, $this->createStub(OrganizationMemberRepositoryPort::class), $this->policy(), $this->reviewers(
      ['user-reviewer-1' => ['organization.interventions.review'], 'user-reviewer-2' => ['organization.interventions.review']],
    ))->submitted('intervention-7', 'Annual inventory', self::ORGANIZATION_ID, 'user-reviewer-1');
  }

  #[Test]
  public function itDropsTheEmailChannelForSubmissionsWhenEmailDeliveryIsDisabled(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => [NotificationChannel::MERCURE] === $request->channels))
      ->willReturn($this->sent());

    new InterventionNotificationService($notifications, $this->createStub(OrganizationMemberRepositoryPort::class), $this->policy(emailEnabled: false), $this->reviewers(
      ['user-reviewer-1' => ['organization.interventions.review']],
    ))->submitted('intervention-7', 'Annual inventory', self::ORGANIZATION_ID, 'user-submitter');
  }

  #[Test]
  public function itDoesNotNotifySubmissionWhenAllChannelsAreDisabled(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new InterventionNotificationService($notifications, $this->createStub(OrganizationMemberRepositoryPort::class), $this->policy(inAppEnabled: false, emailEnabled: false), $this->reviewers(
      ['user-reviewer-1' => ['organization.interventions.review']],
    ))->submitted('intervention-7', 'Annual inventory', self::ORGANIZATION_ID, 'user-submitter');

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itKeepsNotifyingRemainingReviewersWhenOneDeliveryFails(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))
      ->method('send')
      ->willThrowException(new RuntimeException('Mercure unavailable'));

    // Both reviewers are attempted despite the first failure, and the
    // submission itself never bubbles the delivery error.
    new InterventionNotificationService($notifications, $this->createStub(OrganizationMemberRepositoryPort::class), $this->policy(), $this->reviewers(
      ['user-reviewer-1' => ['organization.interventions.review'], 'user-reviewer-2' => ['organization.interventions.review']],
    ))->submitted('intervention-7', 'Annual inventory', self::ORGANIZATION_ID, 'user-submitter');

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

  /**
   * Builds a real resolver over stubbed ports: one active member per entry,
   * whose effective permissions are the entry's value.
   *
   * @param array<string, list<string>> $permissionsByUserId
   */
  private function reviewers(array $permissionsByUserId = []): InterventionReviewerRecipientResolver
  {
    $organizationMembers = [];
    $suffix = 0x40;
    foreach (array_keys($permissionsByUserId) as $userId) {
      $organizationMembers[] = OrganizationMember::reconstitute(
        OrganizationMemberId::fromString(sprintf('018f0b68-6758-7a12-8a1d-3f0d97f63c%02x', ++$suffix)),
        OrganizationId::fromString(self::ORGANIZATION_ID),
        $userId,
        true,
        new DateTimeImmutable(),
      );
    }

    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationId')->willReturn($organizationMembers);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturnCallback(
      static fn (string $userId): array => $permissionsByUserId[$userId] ?? [],
    );

    return new InterventionReviewerRecipientResolver($members, $authorization);
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
