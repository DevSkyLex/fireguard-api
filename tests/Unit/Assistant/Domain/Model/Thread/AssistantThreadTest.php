<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Domain\Model\Thread;

use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\AssistantThreadId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantThreadTest.
 *
 * @category Model Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantThread::class)]
final class AssistantThreadTest extends TestCase
{
  private const string THREAD_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c02';

  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c03';

  private const string MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c04';

  #[Test]
  public function testStartCreatesAThreadWithNoModelYet(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $thread = AssistantThread::start(
      id: AssistantThreadId::fromString(self::THREAD_ID),
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
      title: 'Fire safety questions',
      now: $now,
    );

    self::assertSame(self::ORG_ID, $thread->organizationId());
    self::assertSame(self::MEMBER_ID, $thread->memberId());
    self::assertSame('Fire safety questions', $thread->title());
    self::assertNull($thread->model());
    self::assertSame($now, $thread->createdAt());
    self::assertSame($now, $thread->updatedAt());
    self::assertNull($thread->lastMessageAt());
  }

  #[Test]
  public function testStartAcceptsATenantSelectedModelOverride(): void
  {
    $thread = AssistantThread::start(
      id: AssistantThreadId::fromString(self::THREAD_ID),
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
      title: null,
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      model: 'llama3',
    );

    self::assertSame('llama3', $thread->model());
  }

  #[Test]
  public function testBelongsToMatchesOrganizationAndMember(): void
  {
    $thread = $this->startedThread();

    self::assertTrue($thread->belongsTo(self::ORG_ID, self::MEMBER_ID));
    self::assertFalse($thread->belongsTo(self::ORG_ID, 'someone-else'));
    self::assertFalse($thread->belongsTo('another-org', self::MEMBER_ID));
  }

  #[Test]
  public function testRecordActivityBumpsLastMessageAtAndUpdatedAt(): void
  {
    $thread = $this->startedThread();

    $activityAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
    $thread->recordActivity($activityAt);

    self::assertSame($activityAt, $thread->lastMessageAt());
    self::assertSame($activityAt, $thread->updatedAt());
  }

  #[Test]
  public function testReconstituteRoundTripsPersistedState(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
    $lastMessageAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');

    $thread = AssistantThread::reconstitute(
      id: AssistantThreadId::fromString(self::THREAD_ID),
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
      title: null,
      model: 'llama3',
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      lastMessageAt: $lastMessageAt,
    );

    self::assertSame(self::THREAD_ID, (string) $thread->id());
    self::assertNull($thread->title());
    self::assertSame('llama3', $thread->model());
    self::assertSame($createdAt, $thread->createdAt());
    self::assertSame($updatedAt, $thread->updatedAt());
    self::assertSame($lastMessageAt, $thread->lastMessageAt());
  }

  private function startedThread(): AssistantThread
  {
    return AssistantThread::start(
      id: AssistantThreadId::fromString(self::THREAD_ID),
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
      title: null,
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
}
