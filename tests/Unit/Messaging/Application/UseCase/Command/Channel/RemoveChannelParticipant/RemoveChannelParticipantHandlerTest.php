<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Channel\RemoveChannelParticipant;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Channel\RemoveChannelParticipant\{RemoveChannelParticipantCommand, RemoveChannelParticipantHandler};
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ChannelName, ConversationId, MessagingSubjectType};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test RemoveChannelParticipantHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RemoveChannelParticipantHandler::class)]
final class RemoveChannelParticipantHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string USER_ID = 'user-1';

  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string MEMBER_ID = 'member-2';

  #[Test]
  public function testInvokeRemovesTheParticipant(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->channel());

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::once())->method('removeParticipant')->with(self::CHANNEL_ID, self::MEMBER_ID);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = $this->handler($conversations, $participants, $eventDispatcher);

    $result = $handler->__invoke(new RemoveChannelParticipantCommand(self::USER_ID, self::CHANNEL_ID, self::MEMBER_ID));

    self::assertSame(self::MEMBER_ID, $result->memberId);
  }

  #[Test]
  public function testInvokeRejectsRemovingAParticipantFromATeamBoundChannel(): void
  {
    $channel = $this->channel();
    $channel->bindTeam('team-1');

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($channel);

    $handler = $this->handler(
      $conversations,
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new RemoveChannelParticipantCommand(self::USER_ID, self::CHANNEL_ID, self::MEMBER_ID));
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

    $handler = $this->handler(
      $conversations,
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new RemoveChannelParticipantCommand(self::USER_ID, self::CHANNEL_ID, self::MEMBER_ID));
  }

  private function handler(
    MessagingConversationRepositoryPort $conversations,
    MessagingParticipantRepositoryPort $participants,
    EventDispatcherPort $eventDispatcher,
  ): RemoveChannelParticipantHandler {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);

    return new RemoveChannelParticipantHandler($conversations, $participants, $accessPolicy, $eventDispatcher);
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
}
