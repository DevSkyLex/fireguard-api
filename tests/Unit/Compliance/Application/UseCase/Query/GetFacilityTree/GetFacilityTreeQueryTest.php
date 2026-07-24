<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\UseCase\Query\GetFacilityTree;

use Compliance\Application\UseCase\Query\GetFacilityTree\GetFacilityTreeQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\QueryMessage;

/**
 * Test GetFacilityTreeQueryTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityTreeQuery::class)]
final class GetFacilityTreeQueryTest extends TestCase
{
  #[Test]
  public function testConstructorExposesItsProperties(): void
  {
    $query = new GetFacilityTreeQuery(organizationId: 'org-1', userId: 'user-2');

    self::assertInstanceOf(QueryMessage::class, $query);
    self::assertSame('org-1', $query->organizationId);
    self::assertSame('user-2', $query->userId);
  }
}
