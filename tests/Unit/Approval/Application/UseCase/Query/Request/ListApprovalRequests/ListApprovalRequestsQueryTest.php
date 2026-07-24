<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Query\Request\ListApprovalRequests;

use Approval\Application\UseCase\Query\Request\ListApprovalRequests\ListApprovalRequestsQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListApprovalRequestsQuery.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListApprovalRequestsQuery::class)]
final class ListApprovalRequestsQueryTest extends TestCase
{
  #[Test]
  public function testExposesConstructorArguments(): void
  {
    $query = new ListApprovalRequestsQuery('org-1', 'user-1', 'pending', 'nc_waiver', 2, 50);

    self::assertSame('org-1', $query->organizationId);
    self::assertSame('user-1', $query->userId);
    self::assertSame('pending', $query->status);
    self::assertSame('nc_waiver', $query->actionType);
    self::assertSame(2, $query->page);
    self::assertSame(50, $query->itemsPerPage);
  }

  #[Test]
  public function testAppliesDefaults(): void
  {
    $query = new ListApprovalRequestsQuery('org-1', 'user-1');

    self::assertNull($query->status);
    self::assertNull($query->actionType);
    self::assertSame(1, $query->page);
    self::assertSame(30, $query->itemsPerPage);
  }
}
