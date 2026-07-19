<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Factory;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Contract\Reaction\MessageReactionView;
use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingReactionRepositoryPort, MessagingSavedMessageRepositoryPort};
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

    $factory = new MessageOutputFactory($attachments, new MessageAttachmentOutputFactory(), $reactions, $savedMessages);

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

    $factory = new MessageOutputFactory($attachments, new MessageAttachmentOutputFactory(), $reactions, $savedMessages);

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

  /**
   * @param list<MessageReactionView> $reactions
   * @param list<string> $savedMessageIds
   */
  private function factory(array $reactions, array $savedMessageIds = []): MessageOutputFactory
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findByMessageIds')->willReturn([]);

    $reactionsPort = $this->createStub(MessagingReactionRepositoryPort::class);
    $reactionsPort->method('findByMessageIds')->willReturn($reactions);

    $savedMessages = $this->createStub(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->method('findSavedMessageIds')->willReturn($savedMessageIds);

    return new MessageOutputFactory($attachments, new MessageAttachmentOutputFactory(), $reactionsPort, $savedMessages);
  }

  private function view(bool $deleted = false, ?string $id = null, int $replyCount = 0): MessageView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new MessageView(
      $id ?? self::MESSAGE_ID,
      'conversation-1',
      'org-1',
      'author-1',
      'Hello team',
      [],
      null,
      $deleted ? $now : null,
      $deleted ? 'author-1' : null,
      $now,
      $now,
      null,
      null,
      null,
      $replyCount,
    );
  }
}
