<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Service;

use DateTimeImmutable;
use Messaging\Application\Port\Outbound\MessagingMemberDirectoryPort;
use Messaging\Application\Service\MessagingNotificationService;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\OrganizationNotificationPolicyPort;
use Organization\Domain\ValueObject\OrganizationNotificationSettings;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test MessagingNotificationServiceTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingNotificationService::class)]
final class MessagingNotificationServiceTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string MEMBER_ID = 'member-1';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testMentionedDeliversInAppAndByEmailWhenBothChannelsEnabled(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('memberIsActive')->willReturn(true);
    $members->method('resolveUserIdForMember')->willReturn(self::USER_ID);

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'messaging.mention' === $request->type
        && [NotificationChannel::MERCURE, NotificationChannel::EMAIL] === $request->channels
        && self::USER_ID === $request->recipientUserId
        && 'conversation-1' === $request->payload['conversationId']
        && self::ORG_ID === $request->organizationId))
      ->willReturn($this->sent());

    new MessagingNotificationService($notifications, $members, $this->policy())
      ->mentioned(self::ORG_ID, 'conversation-1', 'facility', 'facility-1', self::MEMBER_ID);
  }

  #[Test]
  public function testMentionedSkipsAnInactiveMember(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('memberIsActive')->willReturn(false);

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new MessagingNotificationService($notifications, $members, $this->policy())
      ->mentioned(self::ORG_ID, 'conversation-1', 'facility', 'facility-1', self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testMentionedDropsTheEmailChannelWhenEmailDeliveryIsDisabled(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('memberIsActive')->willReturn(true);
    $members->method('resolveUserIdForMember')->willReturn(self::USER_ID);

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => [NotificationChannel::MERCURE] === $request->channels))
      ->willReturn($this->sent());

    new MessagingNotificationService($notifications, $members, $this->policy(emailEnabled: false))
      ->mentioned(self::ORG_ID, 'conversation-1', 'facility', 'facility-1', self::MEMBER_ID);
  }

  #[Test]
  public function testMentionedNeverFailsWhenNotificationDeliveryThrows(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('memberIsActive')->willReturn(true);
    $members->method('resolveUserIdForMember')->willReturn(self::USER_ID);

    $notifications = $this->createStub(NotificationPort::class);
    $notifications->method('send')->willThrowException(new RuntimeException('Mercure unavailable'));

    new MessagingNotificationService($notifications, $members, $this->policy())
      ->mentioned(self::ORG_ID, 'conversation-1', 'facility', 'facility-1', self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testChannelMessagePostedIncludesTheOrganizationIdAndExcludesTheAuthor(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('memberIsActive')->willReturn(true);
    $members->method('resolveUserIdForMember')->willReturn(self::USER_ID);

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'messaging.channel_message' === $request->type
        && self::USER_ID === $request->recipientUserId
        && 'conversation-1' === $request->payload['conversationId']
        && self::ORG_ID === $request->organizationId))
      ->willReturn($this->sent());

    new MessagingNotificationService($notifications, $members, $this->policy())
      ->channelMessagePosted(self::ORG_ID, 'conversation-1', [self::MEMBER_ID, 'author-member'], 'author-member');
  }

  #[Test]
  public function testMentionedSkipsWhenNoUserResolvesForTheMember(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('memberIsActive')->willReturn(true);
    $members->method('resolveUserIdForMember')->willReturn(null);

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new MessagingNotificationService($notifications, $members, $this->policy())
      ->mentioned(self::ORG_ID, 'conversation-1', 'facility', 'facility-1', self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testMentionedDropsTheInAppChannelWhenInAppDeliveryIsDisabled(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('memberIsActive')->willReturn(true);
    $members->method('resolveUserIdForMember')->willReturn(self::USER_ID);

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => [NotificationChannel::EMAIL] === $request->channels))
      ->willReturn($this->sent());

    new MessagingNotificationService($notifications, $members, $this->policy(inAppEnabled: false))
      ->mentioned(self::ORG_ID, 'conversation-1', 'facility', 'facility-1', self::MEMBER_ID);
  }

  #[Test]
  public function testMentionedSendsNothingWhenAllChannelsAreDisabled(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('memberIsActive')->willReturn(true);
    $members->method('resolveUserIdForMember')->willReturn(self::USER_ID);

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new MessagingNotificationService($notifications, $members, $this->policy(inAppEnabled: false, emailEnabled: false))
      ->mentioned(self::ORG_ID, 'conversation-1', 'facility', null, self::MEMBER_ID);

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testChannelMessagePostedSendsNothingWhenAllChannelsAreDisabled(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    new MessagingNotificationService($notifications, $members, $this->policy(inAppEnabled: false, emailEnabled: false))
      ->channelMessagePosted(self::ORG_ID, 'conversation-1', [self::MEMBER_ID], 'author-member');

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testChannelMessagePostedSkipsInactiveAndUnresolvableParticipants(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('memberIsActive')->willReturnMap([
      [self::ORG_ID, 'inactive-member', false],
      [self::ORG_ID, 'unresolved-member', true],
      [self::ORG_ID, 'active-member', true],
    ]);
    $members->method('resolveUserIdForMember')->willReturnMap([
      [self::ORG_ID, 'unresolved-member', null],
      [self::ORG_ID, 'active-member', self::USER_ID],
    ]);

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'messaging.channel_message' === $request->type
        && self::USER_ID === $request->recipientUserId
        && 'conversation-1' === $request->payload['conversationId']))
      ->willReturn($this->sent());

    new MessagingNotificationService($notifications, $members, $this->policy())
      ->channelMessagePosted(
        self::ORG_ID,
        'conversation-1',
        ['inactive-member', 'unresolved-member', 'active-member', 'author-member'],
        'author-member',
      );
  }

  #[Test]
  public function testChannelMessagePostedNeverFailsWhenNotificationDeliveryThrows(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('memberIsActive')->willReturn(true);
    $members->method('resolveUserIdForMember')->willReturn(self::USER_ID);

    $notifications = $this->createStub(NotificationPort::class);
    $notifications->method('send')->willThrowException(new RuntimeException('Mercure unavailable'));

    new MessagingNotificationService($notifications, $members, $this->policy())
      ->channelMessagePosted(self::ORG_ID, 'conversation-1', [self::MEMBER_ID], 'author-member');

    self::addToAssertionCount(1);
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
      'notification-1',
      'messaging.mention',
      'Mentioned in a discussion',
      'A teammate mentioned you in a discussion.',
      [NotificationChannel::MERCURE->value],
      [],
      [NotificationChannel::MERCURE->value => true],
      new DateTimeImmutable(),
      self::USER_ID,
      null,
    );
  }
}
