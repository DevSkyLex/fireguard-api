<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\Contract\Thread;

use Assistant\Application\Contract\Thread\AssistantThreadView;
use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\AssistantThreadId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantThreadView.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantThreadView::class)]
final class AssistantThreadViewTest extends TestCase
{
  private const string THREAD_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c02';

  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c03';

  private const string MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c04';

  #[Test]
  public function testConstructorRoundTripsEveryProperty(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
    $lastMessageAt = new DateTimeImmutable('2026-01-03T00:00:00+00:00');

    $view = new AssistantThreadView(
      id: self::THREAD_ID,
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
      title: 'Fire safety questions',
      model: 'llama3',
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      lastMessageAt: $lastMessageAt,
    );

    self::assertSame(self::THREAD_ID, $view->id);
    self::assertSame(self::ORG_ID, $view->organizationId);
    self::assertSame(self::MEMBER_ID, $view->memberId);
    self::assertSame('Fire safety questions', $view->title);
    self::assertSame('llama3', $view->model);
    self::assertSame($createdAt, $view->createdAt);
    self::assertSame($updatedAt, $view->updatedAt);
    self::assertSame($lastMessageAt, $view->lastMessageAt);
  }

  #[Test]
  public function testFromDomainProjectsTheAggregate(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $thread = AssistantThread::start(
      id: AssistantThreadId::fromString(self::THREAD_ID),
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
      title: 'Fire safety questions',
      now: $now,
    );

    $view = AssistantThreadView::fromDomain($thread);

    self::assertSame(self::THREAD_ID, $view->id);
    self::assertSame(self::ORG_ID, $view->organizationId);
    self::assertSame(self::MEMBER_ID, $view->memberId);
    self::assertSame('Fire safety questions', $view->title);
    self::assertNull($view->model);
    self::assertSame($now, $view->createdAt);
    self::assertSame($now, $view->updatedAt);
    self::assertNull($view->lastMessageAt);
  }
}
