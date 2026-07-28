<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Factory;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Contract\Reaction\MessageReactionView;
use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingMemberDirectoryPort, MessagingReactionRepositoryPort, MessagingSavedMessageRepositoryPort};
use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Domain\ValueObject\MessagingAttachmentId;
use Messaging\Presentation\Api\Factory\{MessageAttachmentOutputFactory, MessageOutputFactory};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Test MessageOutputFactoryTest.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessageOutputFactory::class)]
final class MessageOutputFactoryTest extends TestCase
{
  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440000';

  private const string CURRENT_MEMBER_ID = 'member-current';

  private const string OTHER_MEMBER_ID = 'member-other';

  #[Test]
  public function testFromViewAggregatesReactionsByEmojiWithCountAndReactedByMe(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $reactions = [
      new MessageReactionView(self::MESSAGE_ID, self::CURRENT_MEMBER_ID, "\u{1F44D}", $now),
      new MessageReactionView(self::MESSAGE_ID, self::OTHER_MEMBER_ID, "\u{1F44D}", $now),
      new MessageReactionView(self::MESSAGE_ID, self::OTHER_MEMBER_ID, "\u{1F389}", $now),
    ];

    $factory = $this->factory($reactions);

    $output = $factory->fromView($this->view(), self::CURRENT_MEMBER_ID);

    self::assertCount(2, $output->reactions);

    $byEmoji = [];
    foreach ($output->reactions as $reaction) {
      $byEmoji[$reaction['emoji']] = $reaction;
    }

    self::assertSame(2, $byEmoji["\u{1F44D}"]['count']);
    self::assertTrue($byEmoji["\u{1F44D}"]['reactedByMe']);
    self::assertSame(1, $byEmoji["\u{1F389}"]['count']);
    self::assertFalse($byEmoji["\u{1F389}"]['reactedByMe']);
  }

  #[Test]
  public function testFromViewNeverLeaksAnotherMembersReactedByMeFlag(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $reactions = [
      new MessageReactionView(self::MESSAGE_ID, self::OTHER_MEMBER_ID, "\u{1F44D}", $now),
    ];

    $factory = $this->factory($reactions);

    $output = $factory->fromView($this->view(), self::CURRENT_MEMBER_ID);

    self::assertSame(1, $output->reactions[0]['count']);
    self::assertFalse($output->reactions[0]['reactedByMe'], 'Another member\'s reaction must never surface as reactedByMe for the current member.');
  }

  #[Test]
  public function testFromViewOrdersReactionsDeterministicallyByEmoji(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    // Inserted out of order on purpose.
    $reactions = [
      new MessageReactionView(self::MESSAGE_ID, self::OTHER_MEMBER_ID, "\u{1F389}", $now),
      new MessageReactionView(self::MESSAGE_ID, self::OTHER_MEMBER_ID, "\u{1F44D}", $now),
      new MessageReactionView(self::MESSAGE_ID, self::OTHER_MEMBER_ID, "\u{2764}", $now),
    ];

    $factory = $this->factory($reactions);

    $firstOutput = $factory->fromView($this->view(), self::CURRENT_MEMBER_ID);
    $secondOutput = $factory->fromView($this->view(), self::CURRENT_MEMBER_ID);

    $firstEmojis = array_map(static fn (array $r): string => $r['emoji'], $firstOutput->reactions);
    $secondEmojis = array_map(static fn (array $r): string => $r['emoji'], $secondOutput->reactions);

    self::assertSame($firstEmojis, $secondEmojis, 'Ordering must be deterministic across calls.');
  }

  #[Test]
  public function testFromViewRedactsReactionsWhenTheMessageIsDeleted(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $reactions = [
      new MessageReactionView(self::MESSAGE_ID, self::OTHER_MEMBER_ID, "\u{1F44D}", $now),
    ];

    $factory = $this->factory($reactions);

    $output = $factory->fromView($this->view(deleted: true), self::CURRENT_MEMBER_ID);

    self::assertSame([], $output->reactions);
  }

  #[Test]
  public function testFromViewsBatchLoadsReactionsForAWholePageInASingleQuery(): void
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findByMessageIds')->willReturn([]);

    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $secondMessageId = '550e8400-e29b-41d4-a716-446655440099';

    $reactions = $this->createMock(MessagingReactionRepositoryPort::class);
    $reactions->expects(self::once())
      ->method('findByMessageIds')
      ->with([self::MESSAGE_ID, $secondMessageId])
      ->willReturn([
        new MessageReactionView(self::MESSAGE_ID, self::CURRENT_MEMBER_ID, "\u{1F44D}", $now),
        new MessageReactionView($secondMessageId, self::OTHER_MEMBER_ID, "\u{1F389}", $now),
      ]);

    $savedMessages = $this->createStub(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->method('findSavedMessageIds')->willReturn([]);

    $factory = new MessageOutputFactory($attachments, new MessageAttachmentOutputFactory(), $reactions, $savedMessages, $this->createStub(MessagingMemberDirectoryPort::class));

    $outputs = $factory->fromViews([$this->view(), $this->view(id: $secondMessageId)], self::CURRENT_MEMBER_ID);

    self::assertCount(1, $outputs[0]->reactions);
    self::assertTrue($outputs[0]->reactions[0]['reactedByMe']);
    self::assertCount(1, $outputs[1]->reactions);
    self::assertFalse($outputs[1]->reactions[0]['reactedByMe']);
  }

  #[Test]
  public function testFromViewMarksIsSavedWhenTheCurrentMemberSavedTheMessage(): void
  {
    $factory = $this->factory([], [self::MESSAGE_ID]);

    $output = $factory->fromView($this->view(), self::CURRENT_MEMBER_ID);

    self::assertTrue($output->isSaved);
  }

  #[Test]
  public function testFromViewLeavesIsSavedFalseWhenTheCurrentMemberNeverSavedTheMessage(): void
  {
    $factory = $this->factory([], []);

    $output = $factory->fromView($this->view(), self::CURRENT_MEMBER_ID);

    self::assertFalse($output->isSaved);
  }

  #[Test]
  public function testFromViewNeverRedactsIsSavedWhenTheMessageIsDeleted(): void
  {
    $factory = $this->factory([], [self::MESSAGE_ID]);

    $output = $factory->fromView($this->view(deleted: true), self::CURRENT_MEMBER_ID);

    self::assertTrue($output->isSaved, 'A save must remain visible even after the message is tombstoned, so the member can still unsave it.');
  }

  #[Test]
  public function testFromViewsBatchLoadsSavedStateForAWholePageInASingleQuery(): void
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findByMessageIds')->willReturn([]);

    $reactions = $this->createStub(MessagingReactionRepositoryPort::class);
    $reactions->method('findByMessageIds')->willReturn([]);

    $secondMessageId = '550e8400-e29b-41d4-a716-446655440099';

    $savedMessages = $this->createMock(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->expects(self::once())
      ->method('findSavedMessageIds')
      ->with(self::CURRENT_MEMBER_ID, [self::MESSAGE_ID, $secondMessageId])
      ->willReturn([self::MESSAGE_ID]);

    $factory = new MessageOutputFactory($attachments, new MessageAttachmentOutputFactory(), $reactions, $savedMessages, $this->createStub(MessagingMemberDirectoryPort::class));

    $outputs = $factory->fromViews([$this->view(), $this->view(id: $secondMessageId)], self::CURRENT_MEMBER_ID);

    self::assertTrue($outputs[0]->isSaved);
    self::assertFalse($outputs[1]->isSaved);
  }

  #[Test]
  public function testFromViewPopulatesReplyCount(): void
  {
    $factory = $this->factory([]);

    $output = $factory->fromView($this->view(replyCount: 3), self::CURRENT_MEMBER_ID);

    self::assertSame(3, $output->replyCount);
  }

  #[Test]
  public function testFromViewNeverRedactsReplyCountWhenTheMessageIsDeleted(): void
  {
    $factory = $this->factory([]);

    $output = $factory->fromView($this->view(deleted: true, replyCount: 2), self::CURRENT_MEMBER_ID);

    self::assertSame(2, $output->replyCount, 'The reply count must remain visible even after the message is tombstoned — the replies themselves are still separate, readable content.');
  }

  #[Test]
  public function testFromViewPopulatesReferences(): void
  {
    $factory = $this->factory([]);

    $references = [['type' => 'facility', 'id' => 'facility-1', 'label' => 'Site Nord', 'code' => null]];
    $output = $factory->fromView($this->view(references: $references), self::CURRENT_MEMBER_ID);

    self::assertSame($references, $output->references);
  }

  #[Test]
  public function testFromViewRedactsReferencesWhenTheMessageIsDeleted(): void
  {
    $factory = $this->factory([]);

    $references = [['type' => 'facility', 'id' => 'facility-1', 'label' => null, 'code' => null]];
    $output = $factory->fromView($this->view(deleted: true, references: $references), self::CURRENT_MEMBER_ID);

    self::assertSame([], $output->references, 'References are part of the message\'s own content and must be redacted on tombstone, unlike replyCount/pinnedAt/isSaved.');
  }

  public function testItCarriesTheAuthorAndMentionDisplayNames(): void
  {
    $factory = $this->factory([], names: ['author-1' => 'Amélie Rousseau', 'member-2' => 'Daniel Anderson']);

    $output = $factory->fromView($this->view(mentions: ['member-2']), self::CURRENT_MEMBER_ID);

    // Carried on the message so a member without `organization.members.read`
    // still reads a name instead of the raw identifier.
    self::assertSame('Amélie Rousseau', $output->authorDisplayName);
    // Indexed by the BARE member id — the token the body carries as `@{uuid}`.
    self::assertSame(['member-2' => 'Daniel Anderson'], $output->mentionNames);
  }

  public function testItLeavesTheNameNullWhenTheDirectoryKnowsNothing(): void
  {
    $factory = $this->factory([]);

    $output = $factory->fromView($this->view(mentions: ['member-2']), self::CURRENT_MEMBER_ID);

    // Never the identifier as a stand-in: the client decides what to render
    // for an unknown member, and a UUID is not a name.
    self::assertNull($output->authorDisplayName);
    self::assertSame([], $output->mentionNames);
  }

  public function testItRedactsMentionNamesOnATombstone(): void
  {
    $factory = $this->factory([], names: ['author-1' => 'Amélie Rousseau', 'member-2' => 'Daniel Anderson']);

    $output = $factory->fromView($this->view(deleted: true, mentions: ['member-2']), self::CURRENT_MEMBER_ID);

    // Mirrors `mentions` itself: a deleted message's content never leaks.
    self::assertSame([], $output->mentionNames);
    // The author is metadata about the message, not its content — same rule
    // as `pinnedAt`/`isSaved`, which also survive the tombstone.
    self::assertSame('Amélie Rousseau', $output->authorDisplayName);
  }

  #[Test]
  public function testFromViewsReturnsAnEmptyListForAnEmptyPage(): void
  {
    $factory = $this->factory([]);

    self::assertSame([], $factory->fromViews([], self::CURRENT_MEMBER_ID));
  }

  #[Test]
  public function testFromViewExposesThePinningMemberAsAnIriAndResolvesItsDisplayName(): void
  {
    $factory = $this->factory([], names: ['author-1' => 'Amélie Rousseau', self::OTHER_MEMBER_ID => 'Daniel Anderson']);

    $output = $factory->fromView($this->view(pinnedByMemberId: self::OTHER_MEMBER_ID), self::CURRENT_MEMBER_ID);

    self::assertSame('/api/organizations/org-1/members/' . self::OTHER_MEMBER_ID, $output->pinnedBy);
    self::assertSame('2026-01-01T00:00:00+00:00', $output->pinnedAt);
  }

  #[Test]
  public function testFromViewsGroupsAttachmentsByTheirOwningMessage(): void
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findByMessageIds')->willReturn([$this->attachment()]);

    $reactions = $this->createStub(MessagingReactionRepositoryPort::class);
    $reactions->method('findByMessageIds')->willReturn([]);

    $savedMessages = $this->createStub(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->method('findSavedMessageIds')->willReturn([]);

    $factory = new MessageOutputFactory($attachments, new MessageAttachmentOutputFactory(), $reactions, $savedMessages, $this->createStub(MessagingMemberDirectoryPort::class));

    $outputs = $factory->fromViews([$this->view(), $this->view(id: '550e8400-e29b-41d4-a716-446655440099')], self::CURRENT_MEMBER_ID);

    self::assertCount(1, $outputs[0]->attachments);
    self::assertSame([], $outputs[1]->attachments);
  }

  private function attachment(): MessagingAttachment
  {
    return MessagingAttachment::create(
      id: MessagingAttachmentId::fromString('550e8400-e29b-41d4-a716-4466554400aa'),
      messageId: self::MESSAGE_ID,
      conversationId: 'conversation-1',
      organizationId: 'org-1',
      uploadedByMemberId: 'author-1',
      fileName: 'report.pdf',
      storagePath: 'messaging/conversation-1/report.pdf',
      mimeType: 'application/pdf',
      size: 1024,
    );
  }

  /**
   * @param list<MessageReactionView> $reactions
   * @param list<string> $savedMessageIds
   * @param array<string, string> $names
   */
  private function factory(array $reactions, array $savedMessageIds = [], array $names = []): MessageOutputFactory
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findByMessageIds')->willReturn([]);

    $reactionsPort = $this->createStub(MessagingReactionRepositoryPort::class);
    $reactionsPort->method('findByMessageIds')->willReturn($reactions);

    $savedMessages = $this->createStub(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->method('findSavedMessageIds')->willReturn($savedMessageIds);

    $directory = $this->createStub(MessagingMemberDirectoryPort::class);
    $directory->method('displayNamesFor')->willReturn($names);

    return new MessageOutputFactory($attachments, new MessageAttachmentOutputFactory(), $reactionsPort, $savedMessages, $directory);
  }

  /**
   * @param list<array{type: string, id: string, label: ?string, code: ?string}> $references
   * @param list<string> $mentions
   */
  private function view(bool $deleted = false, ?string $id = null, int $replyCount = 0, array $references = [], array $mentions = [], ?string $pinnedByMemberId = null): MessageView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new MessageView(
      $id ?? self::MESSAGE_ID,
      'conversation-1',
      'org-1',
      'author-1',
      'Hello team',
      $mentions,
      null,
      $deleted ? $now : null,
      $deleted ? 'author-1' : null,
      $now,
      $now,
      null === $pinnedByMemberId ? null : $now,
      $pinnedByMemberId,
      null,
      $replyCount,
      $references,
    );
  }
}
