<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\UseCase\Query\ListImportJobs;

use Import\Application\UseCase\Query\ListImportJobs\ListImportJobsQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListImportJobsQuery.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListImportJobsQuery::class)]
final class ListImportJobsQueryTest extends TestCase
{
  #[Test]
  public function itExposesAllConstructorValues(): void
  {
    $query = new ListImportJobsQuery(
      userId: 'user-1',
      organizationId: 'org-1',
      kind: 'equipment',
      page: 2,
      itemsPerPage: 15,
    );

    self::assertSame('user-1', $query->userId);
    self::assertSame('org-1', $query->organizationId);
    self::assertSame('equipment', $query->kind);
    self::assertSame(2, $query->page);
    self::assertSame(15, $query->itemsPerPage);
  }

  #[Test]
  public function itDefaultsKindPageAndItemsPerPage(): void
  {
    $query = new ListImportJobsQuery(userId: 'user-1', organizationId: 'org-1');

    self::assertNull($query->kind);
    self::assertSame(1, $query->page);
    self::assertSame(30, $query->itemsPerPage);
  }
}
