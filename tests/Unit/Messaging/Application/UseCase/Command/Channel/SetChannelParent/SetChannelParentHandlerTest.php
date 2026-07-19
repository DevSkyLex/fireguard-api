<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Channel\SetChannelParent;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ChannelView;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingChannelHierarchyGuard};
use Messaging\Application\UseCase\Command\Channel\SetChannelParent\{SetChannelParentCommand, SetChannelParentHandler};
use Messaging\Domain\Exception\{MessagingConflictException, MessagingNotFoundException};
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ChannelName, ConversationId, MessagingSubjectType};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test SetChannelParentHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetChannelParentHandler::class)]
final class SetChannelParentHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string USER_ID = 'user-1';

  private const string CHILD_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string PARENT_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testInvokeSetsTheParentAfterHierarchyGuardValidation(): void
  {
    $child = $this->channel(self::CHILD_ID);
    $parent = $this->channel(self::PARENT_ID);

    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturnMap([
      [self::CHILD_ID, $child],
      [self::PARENT_ID, $parent],
    ]);
    $conversations->expects(self::once())->method('save')->willReturn($this->conversationView());
    $conversations->method('findChannelById')->willReturn($this->channelView());

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = $this->handler($conversations, $eventDispatcher);

    $result = $handler->__invoke(new SetChannelParentCommand(self::USER_ID, self::CHILD_ID, self::PARENT_ID));

    self::assertSame(self::CHILD_ID, $result->channel->id);
    self::assertSame(self::PARENT_ID, $child->parentConversationId());
  }

  #[Test]
  public function testInvokeClearsTheParentWhenGivenNullWithoutConsultingTheHierarchyGuard(): void
  {
    $child = $this->channel(self::CHILD_ID);
    $child->setParent(self::PARENT_ID);

    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->with(self::CHILD_ID)->willReturn($child);
    $conversations->expects(self::once())->method('save')->willReturn($this->conversationView());
    $conversations->method('findChannelById')->willReturn($this->channelView());

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = $this->handler($conversations, $eventDispatcher);

    $handler->__invoke(new SetChannelParentCommand(self::USER_ID, self::CHILD_ID, null));

    self::assertNull($child->parentConversationId());
  }

  #[Test]
  public function testInvokeThrowsWhenTheConversationIsNotAChannel(): void
  {
    $subjectThread = Conversation::create(
      ConversationId::fromString(self::CHILD_ID),
      self::ORG_ID,
      MessagingSubjectType::FACILITY,
      'facility-1',
    );

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($subjectThread);

    $handler = $this->handler($conversations, $this->createStub(EventDispatcherPort::class));

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new SetChannelParentCommand(self::USER_ID, self::CHILD_ID, self::PARENT_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenTheConversationDoesNotExist(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn(null);

    $handler = $this->handler($conversations, $this->createStub(EventDispatcherPort::class));

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new SetChannelParentCommand(self::USER_ID, self::CHILD_ID, self::PARENT_ID));
  }

  #[Test]
  public function testInvokePropagatesTheHierarchyGuardsConflictOnSelfParenting(): void
  {
    $child = $this->channel(self::CHILD_ID);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($child);

    $handler = $this->handler($conversations, $this->createStub(EventDispatcherPort::class));

    $this->expectException(MessagingConflictException::class);

    $handler->__invoke(new SetChannelParentCommand(self::USER_ID, self::CHILD_ID, self::CHILD_ID));
  }

  private function handler(MessagingConversationRepositoryPort $conversations, EventDispatcherPort $eventDispatcher): SetChannelParentHandler
  {
    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);
    $hierarchyGuard = new MessagingChannelHierarchyGuard($conversations);

    return new SetChannelParentHandler($conversations, $accessPolicy, $hierarchyGuard, $eventDispatcher);
  }

  private function channel(string $id): Conversation
  {
    return Conversation::createChannel(
      ConversationId::fromString($id),
      self::ORG_ID,
      new ChannelName('General'),
      'creator-1',
    );
  }

  private function channelView(): ChannelView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ChannelView(self::CHILD_ID, self::ORG_ID, 'General', null, 'creator-1', 2, false, null, 0, $now, $now, self::PARENT_ID);
  }

  private function conversationView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CHILD_ID, self::ORG_ID, 'channel', null, 'participants', null, 0, false, $now, $now, 'General', null, 'creator-1');
  }
}
