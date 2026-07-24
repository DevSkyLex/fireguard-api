<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationQuota;

use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationQuota\{GetOrganizationQuotaHandler, GetOrganizationQuotaQuery, GetOrganizationQuotaResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetOrganizationQuotaHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetOrganizationQuotaHandler::class)]
final class GetOrganizationQuotaHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655443301';

  #[Test]
  public function testInvokeReturnsTheQuotaSummaryForTheOrganization(): void
  {
    $summary = [
      ['resource' => 'members', 'used' => 4, 'limit' => 10],
      ['resource' => 'teams', 'used' => 2, 'limit' => null],
    ];

    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())
      ->method('getQuotaSummary')
      ->with(self::ORGANIZATION_ID)
      ->willReturn($summary);

    $handler = new GetOrganizationQuotaHandler($quota);

    $result = $handler->__invoke(new GetOrganizationQuotaQuery(self::ORGANIZATION_ID));

    self::assertInstanceOf(GetOrganizationQuotaResult::class, $result);
    self::assertSame($summary, $result->items);
  }

  #[Test]
  public function testInvokeReturnsAnEmptySummaryWhenNoResourcesAreCapped(): void
  {
    $quota = $this->createStub(OrganizationQuotaPort::class);
    $quota->method('getQuotaSummary')->willReturn([]);

    $handler = new GetOrganizationQuotaHandler($quota);

    $result = $handler->__invoke(new GetOrganizationQuotaQuery(self::ORGANIZATION_ID));

    self::assertSame([], $result->items);
  }
}
