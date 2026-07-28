<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Infrastructure\Persistence\Doctrine\Mapper;

use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\AssistantThreadId;
use Assistant\Infrastructure\Persistence\Doctrine\Mapper\AssistantThreadMapper;
use Assistant\Infrastructure\Persistence\Doctrine\Record\AssistantThreadRecord;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantThreadMapperTest.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantThreadMapper::class)]
final class AssistantThreadMapperTest extends TestCase
{
  // #region Constants
  private const string THREAD_ID = '0199a7c1-0000-7000-8000-0000000000b2';

  private const string ORGANIZATION_ID = '0199a7c1-0000-7000-8000-0000000000c3';

  private const string MEMBER_ID = '0199a7c1-0000-7000-8000-0000000000d4';
  // #endregion

  // #region Methods
  #[Test]
  public function testToDomainRebuildsTheAggregate(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-03T08:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-02-03T09:00:00+00:00');
    $lastMessageAt = new DateTimeImmutable('2026-02-03T09:30:00+00:00');

    $record = new AssistantThreadRecord();
    $record->id = self::THREAD_ID;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->memberId = self::MEMBER_ID;
    $record->title = 'Extinguisher checks';
    $record->model = 'claude-sonnet';
    $record->createdAt = $createdAt;
    $record->updatedAt = $updatedAt;
    $record->lastMessageAt = $lastMessageAt;

    $thread = AssistantThreadMapper::toDomain($record);

    self::assertSame(self::THREAD_ID, (string) $thread->id());
    self::assertSame(self::ORGANIZATION_ID, $thread->organizationId());
    self::assertSame(self::MEMBER_ID, $thread->memberId());
    self::assertSame('Extinguisher checks', $thread->title());
    self::assertSame('claude-sonnet', $thread->model());
    self::assertSame($createdAt, $thread->createdAt());
    self::assertSame($updatedAt, $thread->updatedAt());
    self::assertSame($lastMessageAt, $thread->lastMessageAt());
  }

  #[Test]
  public function testToDomainKeepsNullableFieldsNull(): void
  {
    $timestamp = new DateTimeImmutable('2026-02-03T08:00:00+00:00');

    $record = new AssistantThreadRecord();
    $record->id = self::THREAD_ID;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->memberId = self::MEMBER_ID;
    $record->createdAt = $timestamp;
    $record->updatedAt = $timestamp;

    $thread = AssistantThreadMapper::toDomain($record);

    self::assertNull($thread->title());
    self::assertNull($thread->model());
    self::assertNull($thread->lastMessageAt());
  }

  #[Test]
  public function testToRecordCopiesEveryField(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-04T08:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-02-04T10:00:00+00:00');
    $lastMessageAt = new DateTimeImmutable('2026-02-04T10:05:00+00:00');

    $thread = AssistantThread::reconstitute(
      id: AssistantThreadId::fromString(self::THREAD_ID),
      organizationId: self::ORGANIZATION_ID,
      memberId: self::MEMBER_ID,
      title: 'Evacuation drills',
      model: 'claude-opus',
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      lastMessageAt: $lastMessageAt,
    );

    $record = new AssistantThreadRecord();

    AssistantThreadMapper::toRecord($thread, $record);

    self::assertSame(self::THREAD_ID, $record->id);
    self::assertSame(self::ORGANIZATION_ID, $record->organizationId);
    self::assertSame(self::MEMBER_ID, $record->memberId);
    self::assertSame('Evacuation drills', $record->title);
    self::assertSame('claude-opus', $record->model);
    self::assertSame($createdAt, $record->createdAt);
    self::assertSame($updatedAt, $record->updatedAt);
    self::assertSame($lastMessageAt, $record->lastMessageAt);
  }

  #[Test]
  public function testRoundTripPreservesState(): void
  {
    $timestamp = new DateTimeImmutable('2026-02-05T08:00:00+00:00');

    $original = new AssistantThreadRecord();
    $original->id = self::THREAD_ID;
    $original->organizationId = self::ORGANIZATION_ID;
    $original->memberId = self::MEMBER_ID;
    $original->title = 'Round trip';
    $original->model = null;
    $original->createdAt = $timestamp;
    $original->updatedAt = $timestamp;
    $original->lastMessageAt = null;

    $roundTripped = new AssistantThreadRecord();
    AssistantThreadMapper::toRecord(AssistantThreadMapper::toDomain($original), $roundTripped);

    self::assertSame($original->id, $roundTripped->id);
    self::assertSame($original->title, $roundTripped->title);
    self::assertNull($roundTripped->model);
    self::assertNull($roundTripped->lastMessageAt);
  }
  // #endregion
}
