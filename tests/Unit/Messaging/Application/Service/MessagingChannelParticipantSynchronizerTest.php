<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Service;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingChannelParticipantSynchronizer;
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ChannelName, ConversationId};
use Organization\Application\Port\Inbound\TeamDirectoryPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingChannelParticipantSynchronizerTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingChannelParticipantSynchronizer::class)]
final class MessagingChannelParticipantSynchronizerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string TEAM_ID = 'team-1';

  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testResyncTeamBindingReplacesParticipantsAndSeedsReadMarkersOnlyForNewMembers(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->method('listMemberIds')->willReturn(['member-1']);
    $participants->expects(self::once())
      ->method('replaceParticipants')
      ->with(self::CHANNEL_ID, self::ORG_ID, ['member-1', 'member-2']);

    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::once())->method('upsert')->with(self::CHANNEL_ID, self::ORG_ID, 'member-2');

    $teamDirectory = $this->createStub(TeamDirectoryPort::class);
    $teamDirectory->method('listActiveMemberIds')->willReturn(['member-1', 'member-2']);

    $synchronizer = new MessagingChannelParticipantSynchronizer($conversations, $participants, $readMarkers, $teamDirectory);

    $synchronizer->resyncTeamBinding(self::ORG_ID, self::CHANNEL_ID, self::TEAM_ID);
  }

  #[Test]
  public function testOnTeamMemberAddedAddsToEveryBoundChannelAndSeedsTheirReadMarker(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelIdsBoundToTeam')->willReturn([self::CHANNEL_ID]);

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(false);
    $participants->expects(self::once())->method('addMemberToChannels')->with([self::CHANNEL_ID], self::ORG_ID, 'member-3', 'team');

    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::once())->method('upsert')->with(self::CHANNEL_ID, self::ORG_ID, 'member-3');

    $synchronizer = new MessagingChannelParticipantSynchronizer($conversations, $participants, $readMarkers, $this->createStub(TeamDirectoryPort::class));

    $synchronizer->onTeamMemberAdded(self::ORG_ID, self::TEAM_ID, 'member-3');
  }

  #[Test]
  public function testOnTeamMemberAddedIsANoOpWhenAlreadyAParticipant(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelIdsBoundToTeam')->willReturn([self::CHANNEL_ID]);

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(true);
    $participants->expects(self::never())->method('addMemberToChannels');

    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::never())->method('upsert');

    $synchronizer = new MessagingChannelParticipantSynchronizer($conversations, $participants, $readMarkers, $this->createStub(TeamDirectoryPort::class));

    $synchronizer->onTeamMemberAdded(self::ORG_ID, self::TEAM_ID, 'member-3');
  }

  #[Test]
  public function testOnTeamMemberRemovedRemovesFromEveryBoundChannel(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelIdsBoundToTeam')->willReturn([self::CHANNEL_ID]);

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::once())->method('removeMemberFromChannels')->with([self::CHANNEL_ID], 'member-1');

    $synchronizer = new MessagingChannelParticipantSynchronizer(
      $conversations,
      $participants,
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(TeamDirectoryPort::class),
    );

    $synchronizer->onTeamMemberRemoved(self::ORG_ID, self::TEAM_ID, 'member-1');
  }

  #[Test]
  public function testOnTeamDeletedUnbindsEveryBoundChannelAndRetainsParticipants(): void
  {
    $channel = Conversation::createChannel(
      ConversationId::fromString(self::CHANNEL_ID),
      self::ORG_ID,
      new ChannelName('General'),
      'creator-1',
    );
    $channel->bindTeam(self::TEAM_ID);

    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelIdsBoundToTeam')->willReturn([self::CHANNEL_ID]);
    $conversations->method('findAggregateById')->willReturn($channel);
    $conversations->expects(self::once())->method('save')->with($channel)->willReturn($this->conversationView());

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::never())->method('removeParticipant');
    $participants->expects(self::never())->method('removeMemberFromChannels');

    $synchronizer = new MessagingChannelParticipantSynchronizer(
      $conversations,
      $participants,
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(TeamDirectoryPort::class),
    );

    $synchronizer->onTeamDeleted(self::ORG_ID, self::TEAM_ID);

    self::assertNull($channel->teamId());
  }

  #[Test]
  public function testOnOrganizationMemberRemovedPurgesTheMemberFromEveryChannel(): void
  {
    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::once())->method('removeMemberFromAllChannels')->with(self::ORG_ID, 'member-1');

    $synchronizer = new MessagingChannelParticipantSynchronizer(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $participants,
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(TeamDirectoryPort::class),
    );

    $synchronizer->onOrganizationMemberRemoved(self::ORG_ID, 'member-1');
  }

  #[Test]
  public function testOnTeamMemberAddedIsANoOpWhenNoChannelIsBoundToTeam(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelIdsBoundToTeam')->willReturn([]);

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::never())->method('isParticipant');
    $participants->expects(self::never())->method('addMemberToChannels');

    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::never())->method('upsert');

    $synchronizer = new MessagingChannelParticipantSynchronizer(
      $conversations,
      $participants,
      $readMarkers,
      $this->createStub(TeamDirectoryPort::class),
    );

    $synchronizer->onTeamMemberAdded(self::ORG_ID, self::TEAM_ID, 'member-3');
  }

  #[Test]
  public function testOnTeamMemberRemovedIsANoOpWhenNoChannelIsBoundToTeam(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelIdsBoundToTeam')->willReturn([]);

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::never())->method('removeMemberFromChannels');

    $synchronizer = new MessagingChannelParticipantSynchronizer(
      $conversations,
      $participants,
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(TeamDirectoryPort::class),
    );

    $synchronizer->onTeamMemberRemoved(self::ORG_ID, self::TEAM_ID, 'member-1');
  }

  #[Test]
  public function testOnTeamDeletedSkipsChannelsWhoseAggregateIsMissing(): void
  {
    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelIdsBoundToTeam')->willReturn([self::CHANNEL_ID]);
    $conversations->method('findAggregateById')->willReturn(null);
    $conversations->expects(self::never())->method('save');

    $synchronizer = new MessagingChannelParticipantSynchronizer(
      $conversations,
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(TeamDirectoryPort::class),
    );

    $synchronizer->onTeamDeleted(self::ORG_ID, self::TEAM_ID);
  }

  private function conversationView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CHANNEL_ID, self::ORG_ID, 'channel', null, 'participants', null, 0, false, $now, $now, 'General', null, 'creator-1');
  }
}
