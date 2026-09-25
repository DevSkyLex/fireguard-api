<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Infrastructure\Persistence\Doctrine\Mapper;

use Assistant\Domain\Model\Message\{AssistantMessage, RestoredAssistantMessageAttempt, RestoredAssistantMessageContent, RestoredAssistantMessageTimeline};
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantMessageRole, AssistantMessageStatus};
use Assistant\Infrastructure\Exception\AssistantMessageThreadMissingException;
use Assistant\Infrastructure\Persistence\Doctrine\Mapper\AssistantMessageMapper;
use Assistant\Infrastructure\Persistence\Doctrine\Record\{AssistantMessageRecord, AssistantThreadRecord};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantMessageMapperTest.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantMessageMapper::class)]
final class AssistantMessageMapperTest extends TestCase
{
  // #region Constants
  private const string MESSAGE_ID = '0199a7c1-0000-7000-8000-0000000000a1';

  private const string THREAD_ID = '0199a7c1-0000-7000-8000-0000000000b2';

  private const string ORGANIZATION_ID = '0199a7c1-0000-7000-8000-0000000000c3';
  // #endregion

  // #region Methods
  #[Test]
  public function testToDomainRebuildsTheAggregate(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-02T10:00:00+00:00');
    $completedAt = new DateTimeImmutable('2026-02-02T10:00:05+00:00');

    $record = $this->record($createdAt, $completedAt);
    $attemptExpiresAt = new DateTimeImmutable('2026-02-02T10:05:00+00:00');
    $record->attemptId = 'attempt-1';
    $record->attemptNumber = 2;
    $record->attemptSequence = 4;
    $record->attemptExpiresAt = $attemptExpiresAt;
    $record->questionMessageId = 'question-1';
    $record->temperature = 0.4;

    $message = AssistantMessageMapper::toDomain($record);

    self::assertSame(self::MESSAGE_ID, (string) $message->id());
    self::assertSame(self::THREAD_ID, $message->threadId());
    self::assertSame(self::ORGANIZATION_ID, $message->organizationId());
    self::assertSame(AssistantMessageRole::ASSISTANT, $message->role());
    self::assertSame('Here is the answer.', $message->body());
    self::assertSame(AssistantMessageStatus::COMPLETE, $message->status());
    self::assertNull($message->errorCode());
    self::assertSame(128, $message->tokenCount());
    self::assertSame($createdAt, $message->createdAt());
    self::assertSame($completedAt, $message->completedAt());
    self::assertSame('attempt-1', $message->attemptId());
    self::assertSame(2, $message->attemptNumber());
    self::assertSame(4, $message->attemptSequence());
    self::assertSame($attemptExpiresAt, $message->attemptExpiresAt());
    self::assertSame('question-1', $message->questionMessageId());
    self::assertSame(0.4, $message->temperature());
  }

  #[Test]
  public function testToDomainThrowsWhenTheRecordHasNoThread(): void
  {
    $record = $this->record(new DateTimeImmutable('2026-02-02T10:00:00+00:00'), null);
    $record->thread = null;

    $this->expectException(AssistantMessageThreadMissingException::class);
    $this->expectExceptionMessage('An assistant message record must be associated with a thread.');

    AssistantMessageMapper::toDomain($record);
  }

  #[Test]
  public function testToRecordCopiesEveryField(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-02T11:00:00+00:00');
    $completedAt = new DateTimeImmutable('2026-02-02T11:00:09+00:00');
    $attemptExpiresAt = new DateTimeImmutable('2026-02-02T11:05:00+00:00');

    $message = AssistantMessage::reconstitute(
      id: AssistantMessageId::fromString(self::MESSAGE_ID),
      threadId: self::THREAD_ID,
      organizationId: self::ORGANIZATION_ID,
      role: AssistantMessageRole::USER,
      content: new RestoredAssistantMessageContent('What is the inspection schedule?', AssistantMessageStatus::FAILED, 'model_unavailable', 42),
      attempt: new RestoredAssistantMessageAttempt(
        attemptId: 'attempt-2',
        attemptNumber: 3,
        attemptSequence: 5,
        attemptExpiresAt: $attemptExpiresAt,
        questionMessageId: 'question-2',
        temperature: 0.6,
      ),
      timeline: new RestoredAssistantMessageTimeline($createdAt, $completedAt),
    );

    $record = new AssistantMessageRecord();

    AssistantMessageMapper::toRecord($message, $record);

    self::assertSame(self::MESSAGE_ID, $record->id);
    self::assertSame(self::ORGANIZATION_ID, $record->organizationId);
    self::assertSame('user', $record->role);
    self::assertSame('What is the inspection schedule?', $record->body);
    self::assertSame('failed', $record->status);
    self::assertSame('model_unavailable', $record->errorCode);
    self::assertSame(42, $record->tokenCount);
    self::assertSame($createdAt, $record->createdAt);
    self::assertSame($completedAt, $record->completedAt);
    self::assertSame('attempt-2', $record->attemptId);
    self::assertSame(3, $record->attemptNumber);
    self::assertSame(5, $record->attemptSequence);
    self::assertSame($attemptExpiresAt, $record->attemptExpiresAt);
    self::assertSame('question-2', $record->questionMessageId);
    self::assertSame(0.6, $record->temperature);
  }

  #[Test]
  public function testToRecordKeepsNullableFieldsNull(): void
  {
    $message = AssistantMessage::reconstitute(
      id: AssistantMessageId::fromString(self::MESSAGE_ID),
      threadId: self::THREAD_ID,
      organizationId: self::ORGANIZATION_ID,
      role: AssistantMessageRole::ASSISTANT,
      content: new RestoredAssistantMessageContent('', AssistantMessageStatus::PENDING, null, null),
      attempt: new RestoredAssistantMessageAttempt(),
      timeline: new RestoredAssistantMessageTimeline(new DateTimeImmutable('2026-02-02T12:00:00+00:00'), null),
    );

    $record = new AssistantMessageRecord();

    AssistantMessageMapper::toRecord($message, $record);

    self::assertNull($record->errorCode);
    self::assertNull($record->tokenCount);
    self::assertNull($record->completedAt);
    self::assertSame('pending', $record->status);
  }
  // #endregion

  // #region Helpers
  private function record(DateTimeImmutable $createdAt, ?DateTimeImmutable $completedAt): AssistantMessageRecord
  {
    $thread = new AssistantThreadRecord();
    $thread->id = self::THREAD_ID;

    $record = new AssistantMessageRecord();
    $record->id = self::MESSAGE_ID;
    $record->thread = $thread;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->role = 'assistant';
    $record->body = 'Here is the answer.';
    $record->status = 'complete';
    $record->errorCode = null;
    $record->tokenCount = 128;
    $record->createdAt = $createdAt;
    $record->completedAt = $completedAt;

    return $record;
  }
  // #endregion
}
