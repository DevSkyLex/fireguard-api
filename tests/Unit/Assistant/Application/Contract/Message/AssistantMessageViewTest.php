<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\Contract\Message;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantMessageRole, AssistantMessageStatus};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantMessageView.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantMessageView::class)]
final class AssistantMessageViewTest extends TestCase
{
  private const string MESSAGE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c01';

  private const string THREAD_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c02';

  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c03';

  #[Test]
  public function testConstructorRoundTripsEveryProperty(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $completedAt = new DateTimeImmutable('2026-01-01T00:00:05+00:00');

    $view = new AssistantMessageView(
      id: self::MESSAGE_ID,
      threadId: self::THREAD_ID,
      organizationId: self::ORG_ID,
      role: 'assistant',
      body: 'The answer.',
      status: 'complete',
      errorCode: null,
      tokenCount: 17,
      createdAt: $createdAt,
      completedAt: $completedAt,
    );

    self::assertSame(self::MESSAGE_ID, $view->id);
    self::assertSame(self::THREAD_ID, $view->threadId);
    self::assertSame(self::ORG_ID, $view->organizationId);
    self::assertSame('assistant', $view->role);
    self::assertSame('The answer.', $view->body);
    self::assertSame('complete', $view->status);
    self::assertNull($view->errorCode);
    self::assertSame(17, $view->tokenCount);
    self::assertSame($createdAt, $view->createdAt);
    self::assertSame($completedAt, $view->completedAt);
  }

  #[Test]
  public function testFromDomainProjectsTheAggregate(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $message = AssistantMessage::askUser(
      id: AssistantMessageId::fromString(self::MESSAGE_ID),
      threadId: self::THREAD_ID,
      organizationId: self::ORG_ID,
      body: 'How many extinguishers are overdue?',
      now: $now,
    );

    $view = AssistantMessageView::fromDomain($message);

    self::assertSame(self::MESSAGE_ID, $view->id);
    self::assertSame(self::THREAD_ID, $view->threadId);
    self::assertSame(self::ORG_ID, $view->organizationId);
    self::assertSame(AssistantMessageRole::USER->value, $view->role);
    self::assertSame('How many extinguishers are overdue?', $view->body);
    self::assertSame(AssistantMessageStatus::COMPLETE->value, $view->status);
    self::assertSame($now, $view->createdAt);
  }
}
