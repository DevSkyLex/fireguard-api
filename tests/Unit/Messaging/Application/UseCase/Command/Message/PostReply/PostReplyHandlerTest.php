<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Message\PostReply;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingRealtimePublisherPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingNotificationService, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Command\Message\PostReply\{PostReplyCommand, PostReplyHandler};
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\Model\Message\Message;
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
 * Test PostReplyHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PostReplyHandler::class)]
final class PostReplyHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string USER_ID = 'user-1';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string PARENT_MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string AUTHOR_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string MENTIONED_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440004';

  #[Test]
  public function testInvokePersistsTheReplyBumpsBothCountersPublishesAndNotifiesMentions(): void
  {
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->rootMessage());
    $messages->expects(self::once())->method('incrementReplyCount')->with(self::PARENT_MESSAGE_ID);

    $view = $this->messageView(sprintf('Hey @{%s}', self::MENTIONED_MEMBER_ID), [self::MENTIONED_MEMBER_ID]);
    $messages->expects(self::once())->method('append')->willReturn($view);

    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation());
    $conversations->expects(self::once())->method('touchOnNewMessage')->with(self::CONVERSATION_ID);

    $realtime = $this->createMock(MessagingRealtimePublisherPort::class);
    $realtime->expects(self::once())->method('publishMessage')->with(self::ORG_ID, self::CONVERSATION_ID);

    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())->method('send');

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);
    $members->method('memberIsActive')->willReturn(true);
    $members->method('resolveUserIdForMember')->willReturn('mentioned-user-1');

    $handler = $this->handler($conversations, $messages, $realtime, $notificationPort, $members);

    $result = $handler->__invoke(new PostReplyCommand(self::USER_ID, self::PARENT_MESSAGE_ID, 'Hey @{' . self::MENTIONED_MEMBER_ID . '}'));

    self::assertSame($view->body, $result->message->body);
  }

  #[Test]
  public function testInvokeThrowsWhenTheParentMessageIsNotFound(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn(null);

    $handler = $this->handler(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $messages,
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(NotificationPort::class),
      $this->createStub(MessagingMemberDirectoryPort::class),
    );

    $this->expectExceptionMessage(sprintf('Message with ID "%s" not found.', self::PARENT_MESSAGE_ID));

    $handler->__invoke(new PostReplyCommand(self::USER_ID, self::PARENT_MESSAGE_ID, 'A reply'));
  }

  #[Test]
  public function testInvokeThrowsWhenTheParentMessageIsAlreadyDeleted(): void
  {
    $parent = $this->rootMessage();
    $parent->tombstone('author-1');

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($parent);

    $handler = $this->handler(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $messages,
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(NotificationPort::class),
      $this->createStub(MessagingMemberDirectoryPort::class),
    );

    $this->expectException(MessagingValidationException::class);
    $this->expectExceptionMessage('Cannot reply to a deleted message.');

    $handler->__invoke(new PostReplyCommand(self::USER_ID, self::PARENT_MESSAGE_ID, 'A reply'));
  }

  #[Test]
  public function testInvokeThrowsWhenTheParentMessageIsAlreadyAReply(): void
  {
    $nestedParent = Message::create(
      MessageId::fromString(self::PARENT_MESSAGE_ID),
      self::CONVERSATION_ID,
      self::ORG_ID,
      'author-1',
      'A reply to a root message',
      new MentionExtractor(),
      '550e8400-e29b-41d4-a716-446655440099',
    );

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($nestedParent);

    $handler = $this->handler(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $messages,
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(NotificationPort::class),
      $this->createStub(MessagingMemberDirectoryPort::class),
    );

    $this->expectException(MessagingValidationException::class);
    $this->expectExceptionMessage('Cannot reply to a reply; replies only nest one level. Reply to the root message instead.');

    $handler->__invoke(new PostReplyCommand(self::USER_ID, self::PARENT_MESSAGE_ID, 'A reply'));
  }

  #[Test]
  public function testInvokeThrowsWhenTheConversationIsArchived(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->rootMessage());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation(archived: true));

    $handler = $this->handler(
      $conversations,
      $messages,
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(NotificationPort::class),
      $this->createStub(MessagingMemberDirectoryPort::class),
    );

    $this->expectException(MessagingValidationException::class);
    $this->expectExceptionMessage('Cannot post a message to an archived conversation.');

    $handler->__invoke(new PostReplyCommand(self::USER_ID, self::PARENT_MESSAGE_ID, 'A reply'));
  }

  #[Test]
  public function testInvokeNeverFailsWhenRealtimePublishThrows(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->rootMessage());
    $messages->method('append')->willReturn($this->messageView('A reply', []));

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation());

    $realtime = $this->createStub(MessagingRealtimePublisherPort::class);
    $realtime->method('publishMessage')->willThrowException(new RuntimeException('Mercure unavailable'));

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $handler = $this->handler($conversations, $messages, $realtime, $this->createStub(NotificationPort::class), $members);

    $result = $handler->__invoke(new PostReplyCommand(self::USER_ID, self::PARENT_MESSAGE_ID, 'A reply'));

    self::assertSame('A reply', $result->message->body);
  }

  #[Test]
  public function testInvokeThrowsWhenTheParentConversationIsNotFound(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->rootMessage());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn(null);

    $handler = $this->handler(
      $conversations,
      $messages,
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(NotificationPort::class),
      $this->createStub(MessagingMemberDirectoryPort::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new PostReplyCommand(self::USER_ID, self::PARENT_MESSAGE_ID, 'A reply'));
  }

  #[Test]
  public function testInvokeGatesAChannelReplyOnChannelParticipation(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->rootMessage());
    $messages->method('append')->willReturn($this->messageView('A channel reply', []));

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation(visibility: ConversationVisibility::PARTICIPANTS));

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::once())
      ->method('isParticipant')
      ->with(self::CONVERSATION_ID, self::AUTHOR_MEMBER_ID)
      ->willReturn(true);

    $handler = $this->handler(
      $conversations,
      $messages,
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(NotificationPort::class),
      $members,
      $participants,
    );

    $result = $handler->__invoke(new PostReplyCommand(self::USER_ID, self::PARENT_MESSAGE_ID, 'A channel reply'));

    self::assertSame('A channel reply', $result->message->body);
  }

  #[Test]
  public function testInvokeNeverNotifiesTheReplyAuthorForTheirOwnSelfMention(): void
  {
    $body = sprintf('Note to self @{%s}', self::AUTHOR_MEMBER_ID);

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->rootMessage());
    $messages->method('append')->willReturn($this->messageView($body, [self::AUTHOR_MEMBER_ID]));

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);
    $members->method('memberIsActive')->willReturn(true);

    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    $handler = $this->handler(
      $conversations,
      $messages,
      $this->createStub(MessagingRealtimePublisherPort::class),
      $notificationPort,
      $members,
    );

    $result = $handler->__invoke(new PostReplyCommand(self::USER_ID, self::PARENT_MESSAGE_ID, $body));

    self::assertSame($body, $result->message->body);
  }

  private function handler(
    MessagingConversationRepositoryPort $conversations,
    MessagingMessageRepositoryPort $messages,
    MessagingRealtimePublisherPort $realtime,
    NotificationPort $notificationPort,
    MessagingMemberDirectoryPort $members,
    ?MessagingParticipantRepositoryPort $participants = null,
  ): PostReplyHandler {
    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);

    $participants ??= $this->createStub(MessagingParticipantRepositoryPort::class);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);

    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings());
    $notifications = new MessagingNotificationService($notificationPort, $members, $policy);

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(MessageId::fromString('550e8400-e29b-41d4-a716-446655440098'));

    return new PostReplyHandler(
      $conversations,
      $messages,
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

  private function conversation(bool $archived = false, ConversationVisibility $visibility = ConversationVisibility::SUBJECT): Conversation
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return Conversation::reconstitute(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      MessagingSubjectType::FACILITY,
      'facility-1',
      $visibility,
      null,
      0,
      $archived,
      $now,
      $now,
    );
  }

  private function rootMessage(): Message
  {
    return Message::create(
      MessageId::fromString(self::PARENT_MESSAGE_ID),
      self::CONVERSATION_ID,
      self::ORG_ID,
      self::AUTHOR_MEMBER_ID,
      'The root message',
      new MentionExtractor(),
    );
  }

  /**
   * @param list<string> $mentions
   */
  private function messageView(string $body, array $mentions): MessageView
  {
    $now = new DateTimeImmutable('2026-01-02T00:00:00+00:00');

    return new MessageView(
      'reply-1',
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
      null,
      null,
      self::PARENT_MESSAGE_ID,
      0,
    );
  }
}
