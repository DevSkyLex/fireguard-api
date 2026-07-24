<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Query\Thread\GetAssistantThread;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Assistant\Application\Contract\Thread\AssistantThreadView;
use Assistant\Application\UseCase\Query\Thread\GetAssistantThread\GetAssistantThreadResult;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetAssistantThreadResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetAssistantThreadResult::class)]
final class GetAssistantThreadResultTest extends TestCase
{
  #[Test]
  public function testExposesThreadMessagesAndPagination(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $thread = new AssistantThreadView(
      id: 'thread-1',
      organizationId: 'org-1',
      memberId: 'member-1',
      title: 'Fire safety',
      model: null,
      createdAt: $now,
      updatedAt: $now,
      lastMessageAt: null,
    );
    $message = new AssistantMessageView(
      id: 'msg-1',
      threadId: 'thread-1',
      organizationId: 'org-1',
      role: 'user',
      body: 'A question.',
      status: 'complete',
      errorCode: null,
      tokenCount: null,
      createdAt: $now,
      completedAt: $now,
    );

    $result = new GetAssistantThreadResult(
      thread: $thread,
      messages: [$message],
      messagesPage: 1,
      messagesItemsPerPage: 50,
      messagesTotal: 1,
    );

    self::assertSame($thread, $result->thread);
    self::assertSame([$message], $result->messages);
    self::assertSame(1, $result->messagesPage);
    self::assertSame(50, $result->messagesItemsPerPage);
    self::assertSame(1, $result->messagesTotal);
  }
}
