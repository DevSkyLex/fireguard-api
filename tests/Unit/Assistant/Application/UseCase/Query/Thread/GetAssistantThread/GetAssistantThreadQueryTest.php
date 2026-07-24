<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Query\Thread\GetAssistantThread;

use Assistant\Application\UseCase\Query\Thread\GetAssistantThread\GetAssistantThreadQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetAssistantThreadQuery.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetAssistantThreadQuery::class)]
final class GetAssistantThreadQueryTest extends TestCase
{
  #[Test]
  public function testExposesEveryProperty(): void
  {
    $query = new GetAssistantThreadQuery(
      organizationId: 'org-1',
      threadId: 'thread-2',
      actorUserId: 'user-3',
      messagesPage: 2,
      messagesItemsPerPage: 25,
    );

    self::assertSame('org-1', $query->organizationId);
    self::assertSame('thread-2', $query->threadId);
    self::assertSame('user-3', $query->actorUserId);
    self::assertSame(2, $query->messagesPage);
    self::assertSame(25, $query->messagesItemsPerPage);
  }

  #[Test]
  public function testPaginationDefaults(): void
  {
    $query = new GetAssistantThreadQuery(
      organizationId: 'org-1',
      threadId: 'thread-2',
      actorUserId: 'user-3',
    );

    self::assertSame(1, $query->messagesPage);
    self::assertSame(50, $query->messagesItemsPerPage);
  }
}
