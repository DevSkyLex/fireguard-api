<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Query\Request\GetApprovalRequest;

use Approval\Application\UseCase\Query\Request\GetApprovalRequest\GetApprovalRequestQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetApprovalRequestQuery.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetApprovalRequestQuery::class)]
final class GetApprovalRequestQueryTest extends TestCase
{
  #[Test]
  public function testExposesConstructorArguments(): void
  {
    $query = new GetApprovalRequestQuery('org-1', 'req-1', 'user-1');

    self::assertSame('org-1', $query->organizationId);
    self::assertSame('req-1', $query->requestId);
    self::assertSame('user-1', $query->userId);
  }
}
