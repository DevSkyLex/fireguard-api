<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Channel\AddChannelParticipant;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Channel\AddChannelParticipant\{AddChannelParticipantCommand, AddChannelParticipantHandler};
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ChannelName, ConversationId, MessagingSubjectType};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test AddChannelParticipantHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AddChannelParticipantHandler::class)]
final class AddChannelParticipantHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string USER_ID = 'user-1';

  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string MEMBER_ID = 'member-2';

  #[Test]
  public function testInvokeAddsTheParticipantAndSeedsTheirReadMarker(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->channel());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('memberIsActive')->willReturn(true);

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::once())->method('addParticipant');

    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::once())->method('upsert');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = $this->handler($conversations, $participants, $readMarkers, $members, $eventDispatcher);

    $result = $handler->__invoke(new AddChannelParticipantCommand(self::USER_ID, self::CHANNEL_ID, self::MEMBER_ID));

    self::assertSame(self::MEMBER_ID, $result->participant->memberId);
    self::assertSame('manual', $result->participant->source);
  }

  #[Test]
  public function testInvokeRejectsAddingAParticipantToATeamBoundChannel(): void
  {
    $channel = $this->channel();
    $channel->bindTeam('team-1');

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($channel);

    $handler = $this->handler(
      $conversations,
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(MessagingMemberDirectoryPort::class),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new AddChannelParticipantCommand(self::USER_ID, self::CHANNEL_ID, self::MEMBER_ID));
  }

  #[Test]
  public function testInvokeRejectsAnInactiveMember(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->channel());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('memberIsActive')->willReturn(false);

    $handler = $this->handler(
      $conversations,
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $members,
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new AddChannelParticipantCommand(self::USER_ID, self::CHANNEL_ID, self::MEMBER_ID));
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
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(MessagingMemberDirectoryPort::class),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new AddChannelParticipantCommand(self::USER_ID, self::CHANNEL_ID, self::MEMBER_ID));
  }

  private function handler(
    MessagingConversationRepositoryPort $conversations,
    MessagingParticipantRepositoryPort $participants,
    MessagingReadMarkerRepositoryPort $readMarkers,
    MessagingMemberDirectoryPort $members,
    EventDispatcherPort $eventDispatcher,
  ): AddChannelParticipantHandler {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);

    return new AddChannelParticipantHandler($conversations, $participants, $readMarkers, $members, $accessPolicy, $eventDispatcher);
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
