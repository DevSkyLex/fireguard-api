<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Domain\Model\Message;

use Assistant\Domain\Exception\{AssistantMessageIllegalStatusTransitionException, AssistantValidationException};
use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantMessageRole, AssistantMessageStatus};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantMessageTest.
 *
 * @category Model Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantMessage::class)]
final class AssistantMessageTest extends TestCase
{
  private const string MESSAGE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c01';

  private const string THREAD_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c02';

  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c03';

  #[Test]
  public function testAskUserCreatesACompleteMessage(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $message = AssistantMessage::askUser(
      id: AssistantMessageId::fromString(self::MESSAGE_ID),
      threadId: self::THREAD_ID,
      organizationId: self::ORG_ID,
      body: 'How many extinguishers are overdue?',
      now: $now,
    );

    self::assertSame(AssistantMessageRole::USER, $message->role());
    self::assertSame(AssistantMessageStatus::COMPLETE, $message->status());
    self::assertSame($now, $message->completedAt());
  }

  #[Test]
  public function testAskUserRejectsABlankBody(): void
  {
    $this->expectException(AssistantValidationException::class);

    AssistantMessage::askUser(
      id: AssistantMessageId::fromString(self::MESSAGE_ID),
      threadId: self::THREAD_ID,
      organizationId: self::ORG_ID,
      body: '   ',
      now: new DateTimeImmutable(),
    );
  }

  #[Test]
  public function testPendingReplyCreatesAPendingMessage(): void
  {
    $message = $this->pendingReply();

    self::assertSame(AssistantMessageRole::ASSISTANT, $message->role());
    self::assertSame(AssistantMessageStatus::PENDING, $message->status());
    self::assertTrue($message->isPending());
    self::assertSame('', $message->body());
    self::assertNull($message->completedAt());
  }

  #[Test]
  public function testPendingToStreamingIsLegal(): void
  {
    $message = $this->pendingReply();

    $message->markStreaming(new DateTimeImmutable('2026-01-01T00:00:01+00:00'));

    self::assertSame(AssistantMessageStatus::STREAMING, $message->status());
  }

  #[Test]
  public function testStreamingToCompleteReplacesTheBodyInPlace(): void
  {
    $message = $this->pendingReply();
    $message->markStreaming(new DateTimeImmutable('2026-01-01T00:00:01+00:00'));

    $completedAt = new DateTimeImmutable('2026-01-01T00:00:05+00:00');
    $message->markComplete('The final, full reply.', 42, $completedAt);

    self::assertSame(AssistantMessageStatus::COMPLETE, $message->status());
    self::assertSame('The final, full reply.', $message->body());
    self::assertSame(42, $message->tokenCount());
    self::assertSame($completedAt, $message->completedAt());
  }

  #[Test]
  public function testStreamingToFailedIsLegal(): void
  {
    $message = $this->pendingReply();
    $message->markStreaming(new DateTimeImmutable('2026-01-01T00:00:01+00:00'));

    $message->markFailed('upstream_error', new DateTimeImmutable('2026-01-01T00:00:02+00:00'));

    self::assertSame(AssistantMessageStatus::FAILED, $message->status());
    self::assertSame('upstream_error', $message->errorCode());
  }

  #[Test]
  public function testPendingToFailedIsLegalWithoutEverStreaming(): void
  {
    $message = $this->pendingReply();

    $message->markFailed('backend_unreachable', new DateTimeImmutable('2026-01-01T00:00:01+00:00'));

    self::assertSame(AssistantMessageStatus::FAILED, $message->status());
    self::assertSame('backend_unreachable', $message->errorCode());
  }

  #[Test]
  public function testPendingToCompleteDirectlyIsIllegal(): void
  {
    $message = $this->pendingReply();

    $this->expectException(AssistantMessageIllegalStatusTransitionException::class);

    $message->markComplete('skipped streaming', null, new DateTimeImmutable());
  }

  #[Test]
  public function testCompleteIsTerminalAndRejectsAnyFurtherTransition(): void
  {
    $message = $this->pendingReply();
    $message->markStreaming(new DateTimeImmutable('2026-01-01T00:00:01+00:00'));
    $message->markComplete('done', 10, new DateTimeImmutable('2026-01-01T00:00:02+00:00'));

    $this->expectException(AssistantMessageIllegalStatusTransitionException::class);

    // A Messenger retry re-running a settled generation must never be able
    // to re-append/replace a second time — this is the duplicate-answer
    // guard the whole state machine exists for.
    $message->markComplete('done again', 10, new DateTimeImmutable('2026-01-01T00:00:03+00:00'));
  }

  #[Test]
  public function testFailedIsTerminalAndRejectsAnyFurtherTransition(): void
  {
    $message = $this->pendingReply();
    $message->markFailed('backend_unreachable', new DateTimeImmutable('2026-01-01T00:00:01+00:00'));

    $this->expectException(AssistantMessageIllegalStatusTransitionException::class);

    $message->markStreaming(new DateTimeImmutable('2026-01-01T00:00:02+00:00'));
  }

  private function pendingReply(): AssistantMessage
  {
    return AssistantMessage::pendingReply(
      id: AssistantMessageId::fromString(self::MESSAGE_ID),
      threadId: self::THREAD_ID,
      organizationId: self::ORG_ID,
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
}
