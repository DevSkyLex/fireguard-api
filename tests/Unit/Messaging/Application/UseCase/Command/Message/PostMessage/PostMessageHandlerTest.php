<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Message\PostMessage;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingRealtimePublisherPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingNotificationService, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Command\Message\PostMessage\{PostMessageCommand, PostMessageHandler};
use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\{ConversationId, ConversationVisibility, MessageId, MessagingSubjectType};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationNotificationPolicyPort};
use Organization\Domain\ValueObject\OrganizationNotificationSettings;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\LoggerPort;

use function sprintf;

/**
 * Test PostMessageHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PostMessageHandler::class)]
final class PostMessageHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string USER_ID = 'user-1';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string AUTHOR_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string MENTIONED_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testInvokePersistsTheMessageBumpsCountersPublishesAndNotifiesMentions(): void
  {
    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation());
    $conversations->expects(self::once())->method('touchOnNewMessage');

    $view = $this->messageView(sprintf('Hey @{%s}', self::MENTIONED_MEMBER_ID), [self::MENTIONED_MEMBER_ID]);
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::once())->method('append')->willReturn($view);

    $realtime = $this->createMock(MessagingRealtimePublisherPort::class);
    $realtime->expects(self::once())->method('publishMessage')->with(self::ORG_ID, self::CONVERSATION_ID);

    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())->method('send');

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);
    $members->method('memberIsActive')->willReturn(true);
    $members->method('resolveUserIdForMember')->willReturn('mentioned-user-1');

    $handler = $this->handler($conversations, $messages, $realtime, $notificationPort, $members);

    $result = $handler->__invoke(new PostMessageCommand(self::USER_ID, self::CONVERSATION_ID, 'Hey @{' . self::MENTIONED_MEMBER_ID . '}'));

    self::assertSame($view->body, $result->message->body);
  }

  #[Test]
  public function testInvokeNeverNotifiesTheAuthorForASelfMention(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation());

    $view = $this->messageView(sprintf('Note to self @{%s}', self::AUTHOR_MEMBER_ID), [self::AUTHOR_MEMBER_ID]);
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('append')->willReturn($view);

    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $handler = $this->handler($conversations, $messages, $this->createStub(MessagingRealtimePublisherPort::class), $notificationPort, $members);

    $handler->__invoke(new PostMessageCommand(self::USER_ID, self::CONVERSATION_ID, 'Note to self @{' . self::AUTHOR_MEMBER_ID . '}'));

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testInvokeThrowsWhenTheConversationIsArchived(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation(archived: true));

    $handler = $this->handler(
      $conversations,
      $this->createStub(MessagingMessageRepositoryPort::class),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(NotificationPort::class),
      $this->createStub(MessagingMemberDirectoryPort::class),
    );

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new PostMessageCommand(self::USER_ID, self::CONVERSATION_ID, 'Hello'));
  }

  #[Test]
  public function testInvokeNeverFailsWhenRealtimePublishThrows(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation());

    $view = $this->messageView('Hello team', []);
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('append')->willReturn($view);

    $realtime = $this->createStub(MessagingRealtimePublisherPort::class);
    $realtime->method('publishMessage')->willThrowException(new RuntimeException('Mercure unavailable'));

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $handler = $this->handler($conversations, $messages, $realtime, $this->createStub(NotificationPort::class), $members);

    $result = $handler->__invoke(new PostMessageCommand(self::USER_ID, self::CONVERSATION_ID, 'Hello team'));

    self::assertSame('Hello team', $result->message->body);
  }

  private function handler(
    MessagingConversationRepositoryPort $conversations,
    MessagingMessageRepositoryPort $messages,
    MessagingRealtimePublisherPort $realtime,
    NotificationPort $notificationPort,
    MessagingMemberDirectoryPort $members,
    ?MessagingParticipantRepositoryPort $participants = null,
  ): PostMessageHandler {
    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);

    $participants ??= $this->createStub(MessagingParticipantRepositoryPort::class);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);

    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings());
    $notifications = new MessagingNotificationService($notificationPort, $members, $policy);

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(MessageId::fromString('550e8400-e29b-41d4-a716-446655440099'));

    return new PostMessageHandler(
      $conversations,
      $messages,
      $participants,
      $registry,
      $accessPolicy,
      $notifications,
      $realtime,
      new MentionExtractor(),
      $uuidFactory,
      $this->createStub(LoggerPort::class),
    );
  }

  private function facilityResolver(): MessagingSubjectResolverPort
  {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('supports')->willReturnCallback(static fn (MessagingSubjectType $type): bool => MessagingSubjectType::FACILITY === $type);
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution(true, 'Site nord', 'organization.facilities.read'));

    return $resolver;
  }

  private function conversation(bool $archived = false): Conversation
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return Conversation::reconstitute(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      MessagingSubjectType::FACILITY,
      'facility-1',
      ConversationVisibility::SUBJECT,
      null,
      0,
      $archived,
      $now,
      $now,
    );
  }

  /**
   * @param list<string> $mentions
   */
  private function messageView(string $body, array $mentions): MessageView
  {
    $now = new DateTimeImmutable('2026-01-02T00:00:00+00:00');

    return new MessageView(
      'message-1',
      self::CONVERSATION_ID,
      self::ORG_ID,
      self::AUTHOR_MEMBER_ID,
      $body,
      $mentions,
      null,
      null,
      null,
      $now,
      $now,
    );
  }
}
