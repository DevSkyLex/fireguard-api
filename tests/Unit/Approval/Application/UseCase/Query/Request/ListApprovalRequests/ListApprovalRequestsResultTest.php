<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Query\Request\ListApprovalRequests;

use Approval\Application\UseCase\Query\Request\ListApprovalRequests\ListApprovalRequestsResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListApprovalRequestsResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListApprovalRequestsResult::class)]
final class ListApprovalRequestsResultTest extends TestCase
{
  #[Test]
  public function testExposesConstructorArguments(): void
  {
    $result = new ListApprovalRequestsResult(items: [], page: 2, itemsPerPage: 30, total: 5);

    self::assertSame([], $result->items);
    self::assertSame(2, $result->page);
    self::assertSame(30, $result->itemsPerPage);
    self::assertSame(5, $result->total);
  }
}
