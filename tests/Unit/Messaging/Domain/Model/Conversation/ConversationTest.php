<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Model\Conversation;

use DateTimeImmutable;
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ChannelName, ConversationId, ConversationVisibility, MessagingSubjectType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ConversationTest.
 *
 * @category Domain Model Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Conversation::class)]
final class ConversationTest extends TestCase
{
  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORG_ID = 'org-1';

  #[Test]
  public function testCreateStartsUnarchivedWithZeroMessages(): void
  {
    $conversation = Conversation::create(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      MessagingSubjectType::FACILITY,
      'facility-1',
    );

    self::assertFalse($conversation->isArchived());
    self::assertSame(0, $conversation->messagesCount());
    self::assertNull($conversation->lastMessageAt());
    self::assertSame(ConversationVisibility::SUBJECT, $conversation->visibility());
  }

  #[Test]
  public function testArchiveIsIdempotent(): void
  {
    $conversation = Conversation::create(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      MessagingSubjectType::FACILITY,
      'facility-1',
    );

    self::assertTrue($conversation->archive());
    self::assertTrue($conversation->isArchived());
    self::assertFalse($conversation->archive());
  }

  #[Test]
  public function testUnarchiveIsIdempotent(): void
  {
    $conversation = Conversation::reconstitute(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      MessagingSubjectType::FACILITY,
      'facility-1',
      ConversationVisibility::SUBJECT,
      null,
      0,
      true,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    self::assertTrue($conversation->unarchive());
    self::assertFalse($conversation->isArchived());
    self::assertFalse($conversation->unarchive());
  }

  #[Test]
  public function testTouchLastMessageAndIncrementMessagesCount(): void
  {
    $conversation = Conversation::create(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      MessagingSubjectType::INTERVENTION,
      'intervention-1',
    );

    $at = new DateTimeImmutable('2026-02-01T10:00:00+00:00');
    $conversation->touchLastMessage($at);
    $conversation->incrementMessagesCount();

    self::assertSame($at, $conversation->lastMessageAt());
    self::assertSame(1, $conversation->messagesCount());
  }

  #[Test]
  public function testCreateChannelHasNullSubjectAndParticipantsVisibility(): void
  {
    $channel = Conversation::createChannel(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      new ChannelName('General'),
      'member-1',
    );

    self::assertSame(MessagingSubjectType::CHANNEL, $channel->subjectType());
    self::assertNull($channel->subjectId());
    self::assertSame(ConversationVisibility::PARTICIPANTS, $channel->visibility());
    self::assertSame('General', (string) $channel->name());
    self::assertNull($channel->teamId());
    self::assertSame('member-1', $channel->createdByMemberId());
    self::assertFalse($channel->isArchived());
  }

  #[Test]
  public function testRenameChangesTheChannelName(): void
  {
    $channel = Conversation::createChannel(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      new ChannelName('General'),
      'member-1',
    );

    $channel->rename(new ChannelName('Announcements'));

    self::assertSame('Announcements', (string) $channel->name());
  }

  #[Test]
  public function testBindTeamAndUnbindTeam(): void
  {
    $channel = Conversation::createChannel(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      new ChannelName('General'),
      'member-1',
    );

    $channel->bindTeam('team-1');
    self::assertSame('team-1', $channel->teamId());

    $channel->unbindTeam();
    self::assertNull($channel->teamId());
  }

  #[Test]
  public function testReconstituteRestoresChannelFields(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $channel = Conversation::reconstitute(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      MessagingSubjectType::CHANNEL,
      null,
      ConversationVisibility::PARTICIPANTS,
      null,
      0,
      false,
      $now,
      $now,
      new ChannelName('General'),
      'team-1',
      'member-1',
    );

    self::assertSame('General', (string) $channel->name());
    self::assertSame('team-1', $channel->teamId());
    self::assertSame('member-1', $channel->createdByMemberId());
  }

  #[Test]
  public function testNewChannelHasNoParentByDefault(): void
  {
    $channel = Conversation::createChannel(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      new ChannelName('General'),
      'member-1',
    );

    self::assertNull($channel->parentConversationId());
  }

  #[Test]
  public function testSetParentSetsAndClearsTheParentConversationId(): void
  {
    $channel = Conversation::createChannel(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      new ChannelName('General'),
      'member-1',
    );

    $channel->setParent('parent-channel-1');
    self::assertSame('parent-channel-1', $channel->parentConversationId());

    $channel->setParent(null);
    self::assertNull($channel->parentConversationId(), 'Setting the parent to null must detach the channel (make it top-level again).');
  }

  #[Test]
  public function testReconstituteRestoresTheParentConversationId(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $channel = Conversation::reconstitute(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      MessagingSubjectType::CHANNEL,
      null,
      ConversationVisibility::PARTICIPANTS,
      null,
      0,
      false,
      $now,
      $now,
      new ChannelName('Extincteurs — RDC'),
      null,
      'member-1',
      'parent-channel-1',
    );

    self::assertSame('parent-channel-1', $channel->parentConversationId());
  }
}
