<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Channel\UpdateChannel;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ChannelView;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Channel\UpdateChannel\{UpdateChannelCommand, UpdateChannelHandler};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ChannelName, ConversationId, MessagingSubjectType};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test UpdateChannelHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateChannelHandler::class)]
final class UpdateChannelHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string USER_ID = 'user-1';

  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testInvokeRenamesArchivesAndDispatchesTheArchivedEvent(): void
  {
    $channel = $this->channel();

    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($channel);
    $conversations->expects(self::once())->method('save')->willReturn($this->conversationView());
    $conversations->method('findChannelById')->willReturn($this->channelView('Renamed', true));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = $this->handler($conversations, $eventDispatcher);

    $result = $handler->__invoke(new UpdateChannelCommand(self::USER_ID, self::CHANNEL_ID, 'Renamed', true));

    self::assertSame(self::CHANNEL_ID, $result->channel->id);
    self::assertSame('Renamed', $result->channel->name);
    self::assertSame('Renamed', (string) $channel->name());
    self::assertTrue($channel->isArchived());
  }

  #[Test]
  public function testInvokeUnarchivesWithoutDispatchingAnyEvent(): void
  {
    $channel = $this->channel();
    $channel->archive();

    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($channel);
    $conversations->expects(self::once())->method('save')->willReturn($this->conversationView());
    $conversations->method('findChannelById')->willReturn($this->channelView('General', false));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = $this->handler($conversations, $eventDispatcher);

    $handler->__invoke(new UpdateChannelCommand(self::USER_ID, self::CHANNEL_ID, null, false));

    self::assertFalse($channel->isArchived());
  }

  #[Test]
  public function testInvokeDoesNotDispatchWhenTheChannelIsAlreadyArchived(): void
  {
    $channel = $this->channel();
    $channel->archive();

    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($channel);
    $conversations->expects(self::once())->method('save')->willReturn($this->conversationView());
    $conversations->method('findChannelById')->willReturn($this->channelView('General', true));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = $this->handler($conversations, $eventDispatcher);

    $handler->__invoke(new UpdateChannelCommand(self::USER_ID, self::CHANNEL_ID, null, true));

    self::assertTrue($channel->isArchived());
  }

  #[Test]
  public function testInvokeRenamesOnlyWhenTheArchivedFlagIsOmitted(): void
  {
    $channel = $this->channel();

    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($channel);
    $conversations->expects(self::once())->method('save')->willReturn($this->conversationView());
    $conversations->method('findChannelById')->willReturn($this->channelView('Renamed', false));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = $this->handler($conversations, $eventDispatcher);

    $result = $handler->__invoke(new UpdateChannelCommand(self::USER_ID, self::CHANNEL_ID, 'Renamed'));

    self::assertSame('Renamed', (string) $channel->name());
    self::assertFalse($channel->isArchived());
    self::assertSame('Renamed', $result->channel->name);
  }

  #[Test]
  public function testInvokeThrowsWhenTheConversationDoesNotExist(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn(null);

    $handler = $this->handler($conversations, $this->createStub(EventDispatcherPort::class));

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new UpdateChannelCommand(self::USER_ID, self::CHANNEL_ID, 'Renamed'));
  }

  #[Test]
  public function testInvokeThrowsWhenTheConversationIsNotAChannel(): void
  {
    $subjectThread = Conversation::create(
      ConversationId::fromString(self::CHANNEL_ID),
      self::ORG_ID,
      MessagingSubjectType::FACILITY,
      'facility-1',
    );

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($subjectThread);

    $handler = $this->handler($conversations, $this->createStub(EventDispatcherPort::class));

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new UpdateChannelCommand(self::USER_ID, self::CHANNEL_ID, 'Renamed'));
  }

  #[Test]
  public function testInvokeThrowsWhenTheUserCannotManageChannels(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->channel());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')
      ->willThrowException(OrganizationAccessDeniedException::missingPermission('organization.messaging.manage'));

    $handler = $this->handler($conversations, $this->createStub(EventDispatcherPort::class), $authorization);

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new UpdateChannelCommand(self::USER_ID, self::CHANNEL_ID, 'Renamed', true));
  }

  #[Test]
  public function testInvokeThrowsWhenTheChannelViewCannotBeReloadedAfterSaving(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->channel());
    $conversations->method('save')->willReturn($this->conversationView());
    $conversations->method('findChannelById')->willReturn(null);

    $handler = $this->handler($conversations, $this->createStub(EventDispatcherPort::class));

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new UpdateChannelCommand(self::USER_ID, self::CHANNEL_ID, 'Renamed'));
  }

  private function handler(
    MessagingConversationRepositoryPort $conversations,
    EventDispatcherPort $eventDispatcher,
    ?OrganizationAuthorizationPort $authorization = null,
  ): UpdateChannelHandler {
    $authorization ??= $this->createStub(OrganizationAuthorizationPort::class);
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);

    return new UpdateChannelHandler($conversations, $accessPolicy, $eventDispatcher);
  }

  private function channel(): Conversation
  {
    return Conversation::createChannel(
      ConversationId::fromString(self::CHANNEL_ID),
      self::ORG_ID,
      new ChannelName('General'),
      'creator-1',
    );
  }

  private function channelView(string $name, bool $isArchived): ChannelView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ChannelView(self::CHANNEL_ID, self::ORG_ID, $name, null, 'creator-1', 3, $isArchived, null, 0, $now, $now);
  }

  private function conversationView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CHANNEL_ID, self::ORG_ID, 'channel', null, 'participants', null, 0, false, $now, $now, 'General', null, 'creator-1');
  }
}
