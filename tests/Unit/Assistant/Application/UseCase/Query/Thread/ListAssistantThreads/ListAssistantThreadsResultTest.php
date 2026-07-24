<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Query\Thread\ListAssistantThreads;

use Assistant\Application\Contract\Thread\AssistantThreadView;
use Assistant\Application\UseCase\Query\Thread\ListAssistantThreads\ListAssistantThreadsResult;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListAssistantThreadsResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListAssistantThreadsResult::class)]
final class ListAssistantThreadsResultTest extends TestCase
{
  #[Test]
  public function testExposesItemsAndPagination(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $thread = new AssistantThreadView(
      id: 'thread-1',
      organizationId: 'org-1',
      memberId: 'member-1',
      title: null,
      model: null,
      createdAt: $now,
      updatedAt: $now,
      lastMessageAt: null,
    );

    $result = new ListAssistantThreadsResult(
      items: [$thread],
      page: 1,
      itemsPerPage: 30,
      total: 1,
    );

    self::assertSame([$thread], $result->items);
    self::assertSame(1, $result->page);
    self::assertSame(30, $result->itemsPerPage);
    self::assertSame(1, $result->total);
  }

  #[Test]
  public function testAcceptsAnEmptyPage(): void
  {
    $result = new ListAssistantThreadsResult(items: [], page: 1, itemsPerPage: 30, total: 0);

    self::assertSame([], $result->items);
    self::assertSame(0, $result->total);
  }
}
