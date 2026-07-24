<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\UseCase\Query\GetFacilityCompliance;

use Compliance\Application\UseCase\Query\GetFacilityCompliance\GetFacilityComplianceQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\QueryMessage;

/**
 * Test GetFacilityComplianceQueryTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityComplianceQuery::class)]
final class GetFacilityComplianceQueryTest extends TestCase
{
  #[Test]
  public function testConstructorExposesItsProperties(): void
  {
    $query = new GetFacilityComplianceQuery(organizationId: 'org-1', facilityId: 'facility-5', userId: 'user-2');

    self::assertInstanceOf(QueryMessage::class, $query);
    self::assertSame('org-1', $query->organizationId);
    self::assertSame('facility-5', $query->facilityId);
    self::assertSame('user-2', $query->userId);
  }
}
