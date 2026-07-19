<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Model\Message;

use DateTimeImmutable;
use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\MessageId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * Test MessageTest.
 *
 * @category Domain Model Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Message::class)]
final class MessageTest extends TestCase
{
  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string MEMBER_A = '550e8400-e29b-41d4-a716-446655440002';

  private const string MEMBER_B = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testCreateTrimsBodyAndExtractsMentions(): void
  {
    $message = Message::create(
      MessageId::fromString(self::MESSAGE_ID),
      'conversation-1',
      'org-1',
      'author-1',
      sprintf('  Hello @{%s}  ', self::MEMBER_A),
      new MentionExtractor(),
    );

    self::assertSame(sprintf('Hello @{%s}', self::MEMBER_A), $message->body());
    self::assertSame([self::MEMBER_A], $message->mentions());
    self::assertNull($message->editedAt());
    self::assertFalse($message->isDeleted());
  }

  #[Test]
  public function testEditRecomputesMentionsAndReturnsOnlyNewlyAdded(): void
  {
    $extractor = new MentionExtractor();
    $message = Message::create(
      MessageId::fromString(self::MESSAGE_ID),
      'conversation-1',
      'org-1',
      'author-1',
      sprintf('Hi @{%s}', self::MEMBER_A),
      $extractor,
    );

    $newlyMentioned = $message->edit(sprintf('Hi @{%s} and @{%s}', self::MEMBER_A, self::MEMBER_B), $extractor);

    self::assertSame([self::MEMBER_B], $newlyMentioned);
    self::assertSame([self::MEMBER_A, self::MEMBER_B], $message->mentions());
    self::assertNotNull($message->editedAt());
  }

  #[Test]
  public function testEditThrowsWhenMessageIsAlreadyDeleted(): void
  {
    $extractor = new MentionExtractor();
    $message = Message::create(MessageId::fromString(self::MESSAGE_ID), 'conversation-1', 'org-1', 'author-1', 'Hello', $extractor);
    $message->tombstone('author-1');

    $this->expectException(MessagingValidationException::class);

    $message->edit('Updated body', $extractor);
  }

  #[Test]
  public function testTombstoneIsIdempotentAndRetainsBody(): void
  {
    $extractor = new MentionExtractor();
    $message = Message::create(MessageId::fromString(self::MESSAGE_ID), 'conversation-1', 'org-1', 'author-1', 'Hello team', $extractor);

    self::assertTrue($message->tombstone('manager-1'));
    self::assertTrue($message->isDeleted());
    self::assertSame('manager-1', $message->deletedByMemberId());
    self::assertSame('Hello team', $message->body(), 'The persisted body must be retained after tombstone.');

    self::assertFalse($message->tombstone('manager-1'));
  }

  #[Test]
  public function testIsAuthoredBy(): void
  {
    $message = Message::create(MessageId::fromString(self::MESSAGE_ID), 'conversation-1', 'org-1', 'author-1', 'Hello', new MentionExtractor());

    self::assertTrue($message->isAuthoredBy('author-1'));
    self::assertFalse($message->isAuthoredBy('author-2'));
  }

  #[Test]
  public function testReconstitute(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $message = Message::reconstitute(
      MessageId::fromString(self::MESSAGE_ID),
      'conversation-1',
      'org-1',
      'author-1',
      'Persisted body',
      [self::MEMBER_A],
      null,
      null,
      null,
      $now,
      $now,
    );

    self::assertSame('Persisted body', $message->body());
    self::assertSame([self::MEMBER_A], $message->mentions());
    self::assertFalse($message->isDeleted());
  }

  #[Test]
  public function testPinIsIdempotentAndRetainsTheOriginalPinner(): void
  {
    $message = Message::create(MessageId::fromString(self::MESSAGE_ID), 'conversation-1', 'org-1', 'author-1', 'Hello team', new MentionExtractor());

    self::assertTrue($message->pin('pinner-1'));
    self::assertTrue($message->isPinned());
    self::assertSame('pinner-1', $message->pinnedByMemberId());
    self::assertNotNull($message->pinnedAt());

    $firstPinnedAt = $message->pinnedAt();

    // A second pin (even by a different member) is a no-op: it must not
    // "steal" credit for the pin nor bump the pin timestamp.
    self::assertFalse($message->pin('pinner-2'));
    self::assertSame('pinner-1', $message->pinnedByMemberId());
    self::assertSame($firstPinnedAt, $message->pinnedAt());
  }

  #[Test]
  public function testPinThrowsWhenMessageIsAlreadyDeleted(): void
  {
    $message = Message::create(MessageId::fromString(self::MESSAGE_ID), 'conversation-1', 'org-1', 'author-1', 'Hello', new MentionExtractor());
    $message->tombstone('author-1');

    $this->expectException(MessagingValidationException::class);

    $message->pin('pinner-1');
  }

  #[Test]
  public function testUnpinIsIdempotent(): void
  {
    $message = Message::create(MessageId::fromString(self::MESSAGE_ID), 'conversation-1', 'org-1', 'author-1', 'Hello team', new MentionExtractor());
    $message->pin('pinner-1');

    self::assertTrue($message->unpin());
    self::assertFalse($message->isPinned());
    self::assertNull($message->pinnedByMemberId());
    self::assertNull($message->pinnedAt());

    // Unpinning a message that is not pinned must not error.
    self::assertFalse($message->unpin());
  }

  #[Test]
  public function testUnpinNeverThrowsOnAMessageThatWasNeverPinned(): void
  {
    $message = Message::create(MessageId::fromString(self::MESSAGE_ID), 'conversation-1', 'org-1', 'author-1', 'Hello', new MentionExtractor());

    self::assertFalse($message->unpin());
    self::assertFalse($message->isPinned());
  }

  #[Test]
  public function testReconstituteCarriesThePinState(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $pinnedAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
    $message = Message::reconstitute(
      MessageId::fromString(self::MESSAGE_ID),
      'conversation-1',
      'org-1',
      'author-1',
      'Persisted body',
      [],
      null,
      null,
      null,
      $now,
      $now,
      $pinnedAt,
      'pinner-1',
    );

    self::assertTrue($message->isPinned());
    self::assertSame($pinnedAt, $message->pinnedAt());
    self::assertSame('pinner-1', $message->pinnedByMemberId());
  }

  #[Test]
  public function testCreateWithoutAParentIsNotAReply(): void
  {
    $message = Message::create(MessageId::fromString(self::MESSAGE_ID), 'conversation-1', 'org-1', 'author-1', 'Hello team', new MentionExtractor());

    self::assertFalse($message->isReply());
    self::assertNull($message->parentMessageId());
  }

  #[Test]
  public function testCreateWithAParentIsAReply(): void
  {
    $parentId = '550e8400-e29b-41d4-a716-446655440099';

    $message = Message::create(
      MessageId::fromString(self::MESSAGE_ID),
      'conversation-1',
      'org-1',
      'author-1',
      'A reply',
      new MentionExtractor(),
      $parentId,
    );

    self::assertTrue($message->isReply());
    self::assertSame($parentId, $message->parentMessageId());
  }

  #[Test]
  public function testIncrementReplyCount(): void
  {
    $message = Message::create(MessageId::fromString(self::MESSAGE_ID), 'conversation-1', 'org-1', 'author-1', 'Hello team', new MentionExtractor());

    self::assertSame(0, $message->replyCount());

    $message->incrementReplyCount();
    $message->incrementReplyCount();

    self::assertSame(2, $message->replyCount());
  }

  #[Test]
  public function testReconstituteCarriesTheThreadState(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $parentId = '550e8400-e29b-41d4-a716-446655440099';

    $message = Message::reconstitute(
      MessageId::fromString(self::MESSAGE_ID),
      'conversation-1',
      'org-1',
      'author-1',
      'A reply',
      [],
      null,
      null,
      null,
      $now,
      $now,
      null,
      null,
      $parentId,
      3,
    );

    self::assertTrue($message->isReply());
    self::assertSame($parentId, $message->parentMessageId());
    self::assertSame(3, $message->replyCount());
  }
}
