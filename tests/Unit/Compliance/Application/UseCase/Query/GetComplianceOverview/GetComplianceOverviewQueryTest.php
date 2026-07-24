<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\UseCase\Query\GetComplianceOverview;

use Compliance\Application\UseCase\Query\GetComplianceOverview\GetComplianceOverviewQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\QueryMessage;

/**
 * Test GetComplianceOverviewQueryTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetComplianceOverviewQuery::class)]
final class GetComplianceOverviewQueryTest extends TestCase
{
  #[Test]
  public function testConstructorExposesItsProperties(): void
  {
    $query = new GetComplianceOverviewQuery(organizationId: 'org-1', userId: 'user-2');

    self::assertInstanceOf(QueryMessage::class, $query);
    self::assertSame('org-1', $query->organizationId);
    self::assertSame('user-2', $query->userId);
  }
}
