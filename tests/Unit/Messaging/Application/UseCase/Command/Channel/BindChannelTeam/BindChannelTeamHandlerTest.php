<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Channel\BindChannelTeam;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ChannelView;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingChannelParticipantSynchronizer};
use Messaging\Application\UseCase\Command\Channel\BindChannelTeam\{BindChannelTeamCommand, BindChannelTeamHandler};
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ChannelName, ConversationId, MessagingSubjectType};
use Organization\Application\Contract\Team\TeamMembershipSnapshot;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, TeamDirectoryPort};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test BindChannelTeamHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(BindChannelTeamHandler::class)]
final class BindChannelTeamHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string USER_ID = 'user-1';

  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string TEAM_ID = 'team-1';

  #[Test]
  public function testInvokeBindsAndFullyResyncsParticipantsFromTheTeam(): void
  {
    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->channel());
    $conversations->expects(self::once())->method('save')->willReturn($this->conversationView());
    $conversations->method('findChannelById')->willReturn($this->channelView());
    $conversations->method('findChannelIdsBoundToTeam')->willReturn([self::CHANNEL_ID]);

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->method('listMemberIds')->willReturn([]);
    $participants->expects(self::once())
      ->method('replaceParticipants')
      ->with(self::CHANNEL_ID, self::ORG_ID, ['member-1', 'member-2']);

    $teamDirectory = $this->createStub(TeamDirectoryPort::class);
    $teamDirectory->method('resolveTeam')->willReturn(new TeamMembershipSnapshot(self::TEAM_ID, 'Field crew', ['member-1', 'member-2']));
    $teamDirectory->method('listActiveMemberIds')->willReturn(['member-1', 'member-2']);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = $this->handler($conversations, $participants, $teamDirectory, $eventDispatcher);

    $result = $handler->__invoke(new BindChannelTeamCommand(self::USER_ID, self::CHANNEL_ID, self::TEAM_ID));

    self::assertSame(self::CHANNEL_ID, $result->channel->id);
  }

  #[Test]
  public function testInvokeUnbindsWithoutResyncingWhenTeamIdIsNull(): void
  {
    $channel = $this->channel();
    $channel->bindTeam(self::TEAM_ID);

    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($channel);
    $conversations->expects(self::once())->method('save')->willReturn($this->conversationView());
    $conversations->method('findChannelById')->willReturn($this->channelView());

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::never())->method('replaceParticipants');

    $teamDirectory = $this->createMock(TeamDirectoryPort::class);
    $teamDirectory->expects(self::never())->method('listActiveMemberIds');

    $handler = $this->handler($conversations, $participants, $teamDirectory, $this->createStub(EventDispatcherPort::class));

    $handler->__invoke(new BindChannelTeamCommand(self::USER_ID, self::CHANNEL_ID, null));

    self::assertNull($channel->teamId());
  }

  #[Test]
  public function testInvokeThrowsWhenTheTeamDoesNotExist(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->channel());

    $teamDirectory = $this->createStub(TeamDirectoryPort::class);
    $teamDirectory->method('resolveTeam')->willReturn(null);

    $handler = $this->handler(
      $conversations,
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $teamDirectory,
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new BindChannelTeamCommand(self::USER_ID, self::CHANNEL_ID, self::TEAM_ID));
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
      $this->createStub(TeamDirectoryPort::class),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new BindChannelTeamCommand(self::USER_ID, self::CHANNEL_ID, self::TEAM_ID));
  }

  private function handler(
    MessagingConversationRepositoryPort $conversations,
    MessagingParticipantRepositoryPort $participants,
    TeamDirectoryPort $teamDirectory,
    EventDispatcherPort $eventDispatcher,
  ): BindChannelTeamHandler {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);

    $readMarkers = $this->createStub(MessagingReadMarkerRepositoryPort::class);
    $synchronizer = new MessagingChannelParticipantSynchronizer($conversations, $participants, $readMarkers, $teamDirectory);

    return new BindChannelTeamHandler($conversations, $accessPolicy, $synchronizer, $teamDirectory, $eventDispatcher);
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

  private function channelView(): ChannelView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ChannelView(self::CHANNEL_ID, self::ORG_ID, 'General', self::TEAM_ID, 'creator-1', 2, false, null, 0, $now, $now);
  }

  private function conversationView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CHANNEL_ID, self::ORG_ID, 'channel', null, 'participants', null, 0, false, $now, $now, 'General', self::TEAM_ID, 'creator-1');
  }
}
