<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Query\Thread\ListAssistantThreads;

use Assistant\Application\UseCase\Query\Thread\ListAssistantThreads\ListAssistantThreadsQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListAssistantThreadsQuery.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListAssistantThreadsQuery::class)]
final class ListAssistantThreadsQueryTest extends TestCase
{
  #[Test]
  public function testExposesEveryProperty(): void
  {
    $query = new ListAssistantThreadsQuery(
      organizationId: 'org-1',
      actorUserId: 'user-2',
      page: 3,
      itemsPerPage: 15,
    );

    self::assertSame('org-1', $query->organizationId);
    self::assertSame('user-2', $query->actorUserId);
    self::assertSame(3, $query->page);
    self::assertSame(15, $query->itemsPerPage);
  }

  #[Test]
  public function testPaginationDefaults(): void
  {
    $query = new ListAssistantThreadsQuery(
      organizationId: 'org-1',
      actorUserId: 'user-2',
    );

    self::assertSame(1, $query->page);
    self::assertSame(30, $query->itemsPerPage);
  }
}
