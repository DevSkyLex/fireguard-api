<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Infrastructure\EventSubscriber;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingChannelParticipantSynchronizer;
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ChannelName, ConversationId};
use Messaging\Infrastructure\EventSubscriber\SyncTeamChannelParticipantsSubscriber;
use Organization\Application\Port\Inbound\TeamDirectoryPort;
use Organization\Domain\Event\Member\OrganizationMemberRemovedEvent;
use Organization\Domain\Event\Team\{TeamDeletedEvent, TeamMemberAddedEvent, TeamMemberRemovedEvent};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\LoggerPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test SyncTeamChannelParticipantsSubscriberTest.
 *
 * Exercises the subscriber THROUGH a real
 * {@see MessagingChannelParticipantSynchronizer} (a final, un-mockable
 * class) wired with stubbed/mocked outbound ports, asserting on the port
 * calls the synchronizer makes as a result of each Organization event.
 *
 * @category Subscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SyncTeamChannelParticipantsSubscriber::class)]
final class SyncTeamChannelParticipantsSubscriberTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string TEAM_ID = 'team-1';

  private const string MEMBER_ID = 'member-1';

  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testIsAnEventSubscriber(): void
  {
    self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $this->createStub(MessagingParticipantRepositoryPort::class),
    ));
  }

  #[Test]
  public function testGetSubscribedEventsMapsEveryOrganizationEventByName(): void
  {
    $events = SyncTeamChannelParticipantsSubscriber::getSubscribedEvents();

    self::assertArrayHasKey('organization.team_member_added_event', $events);
    self::assertArrayHasKey('organization.team_member_removed_event', $events);
    self::assertArrayHasKey('organization.team_deleted_event', $events);
    self::assertArrayHasKey('organization.organization_member_removed_event', $events);
  }

  #[Test]
  public function testOnTeamMemberAddedAddsTheMemberToEveryBoundChannel(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelIdsBoundToTeam')->willReturn([self::CHANNEL_ID]);

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(false);
    $participants->expects(self::once())->method('addMemberToChannels')->with([self::CHANNEL_ID], self::ORG_ID, self::MEMBER_ID, 'team');

    $this->subscriber($conversations, $participants)->onTeamMemberAdded(new TeamMemberAddedEvent(self::ORG_ID, self::TEAM_ID, self::MEMBER_ID));
  }

  #[Test]
  public function testOnTeamMemberRemovedRemovesTheMemberFromEveryBoundChannel(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelIdsBoundToTeam')->willReturn([self::CHANNEL_ID]);

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::once())->method('removeMemberFromChannels')->with([self::CHANNEL_ID], self::MEMBER_ID);

    $this->subscriber($conversations, $participants)->onTeamMemberRemoved(new TeamMemberRemovedEvent(self::ORG_ID, self::TEAM_ID, self::MEMBER_ID));
  }

  #[Test]
  public function testOnTeamDeletedUnbindsEveryBoundChannel(): void
  {
    $channel = Conversation::createChannel(
      ConversationId::fromString(self::CHANNEL_ID),
      self::ORG_ID,
      new ChannelName('General'),
      'creator-1',
    );
    $channel->bindTeam(self::TEAM_ID);

    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $view = new ConversationView(self::CHANNEL_ID, self::ORG_ID, 'channel', null, 'participants', null, 0, false, $now, $now, 'General', null, 'creator-1');

    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelIdsBoundToTeam')->willReturn([self::CHANNEL_ID]);
    $conversations->method('findAggregateById')->willReturn($channel);
    $conversations->expects(self::once())->method('save')->with($channel)->willReturn($view);

    $this->subscriber($conversations, $this->createStub(MessagingParticipantRepositoryPort::class))
      ->onTeamDeleted(new TeamDeletedEvent(self::ORG_ID, self::TEAM_ID));

    self::assertNull($channel->teamId());
  }

  #[Test]
  public function testOnOrganizationMemberRemovedPurgesTheMemberFromEveryChannel(): void
  {
    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::once())->method('removeMemberFromAllChannels')->with(self::ORG_ID, self::MEMBER_ID);

    $this->subscriber($this->createStub(MessagingConversationRepositoryPort::class), $participants)
      ->onOrganizationMemberRemoved(new OrganizationMemberRemovedEvent(self::ORG_ID, self::MEMBER_ID, 'user-1'));
  }

  #[Test]
  public function testASynchronizerFailureIsSwallowedAndLogged(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelIdsBoundToTeam')->willThrowException(new RuntimeException('Reconciliation failed.'));

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning');

    $synchronizer = new MessagingChannelParticipantSynchronizer(
      $conversations,
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(TeamDirectoryPort::class),
    );

    $subscriber = new SyncTeamChannelParticipantsSubscriber($synchronizer, $logger);

    $subscriber->onTeamMemberAdded(new TeamMemberAddedEvent(self::ORG_ID, self::TEAM_ID, self::MEMBER_ID));

    self::addToAssertionCount(1);
  }

  private function subscriber(
    MessagingConversationRepositoryPort $conversations,
    MessagingParticipantRepositoryPort $participants,
  ): SyncTeamChannelParticipantsSubscriber {
    $synchronizer = new MessagingChannelParticipantSynchronizer(
      $conversations,
      $participants,
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(TeamDirectoryPort::class),
    );

    return new SyncTeamChannelParticipantsSubscriber($synchronizer, $this->createStub(LoggerPort::class));
  }
}
