<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Application\UseCase\Rate;

use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;
use MaintenanceCost\Application\Port\Outbound\Rate\MaintenanceRateStorePort;
use MaintenanceCost\Application\Service\MaintenanceCostAccessGuard;
use MaintenanceCost\Application\UseCase\Query\Rate\ListMaintenanceRates\{ListMaintenanceRatesHandler, ListMaintenanceRatesQuery};
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Rate counts and page data keep the same organization and member scope. */
final class ListMaintenanceRatesHandlerTest extends TestCase
{
  private const string ORG = '650e8400-e29b-41d4-a716-448040000001';

  #[Test]
  public function keepsRatesAndTotalsOnTheSameBoundedScope(): void
  {
    $auth = $this->createMock(OrganizationAuthorizationPort::class);
    $auth->expects(self::once())->method('resolveAccess')->with('actor', self::ORG, 'organization.maintenance_cost.read')->willReturn(OrganizationAccessDecision::GRANTED);
    $rate = new MaintenanceRateSnapshot('id', 'member', '1.000000', 'EUR', '2026-01-01');
    $store = $this->createMock(MaintenanceRateStorePort::class);
    $store->expects(self::once())->method('list')->with(self::ORG, 10, 10, null)->willReturn([$rate]);
    $store->expects(self::once())->method('count')->with(self::ORG, null)->willReturn(11);
    $result = (new ListMaintenanceRatesHandler($store, new MaintenanceCostAccessGuard($auth), $this->createStub(OrganizationWorkforceDirectoryPort::class)))(new ListMaintenanceRatesQuery(self::ORG, 'actor', page: 2, itemsPerPage: 10));
    self::assertSame([$rate], $result->items);
    self::assertSame(11, $result->total);
  }

  #[Test]
  public function operationalMembershipCannotReadPrivateRates(): void
  {
    $auth = $this->createMock(OrganizationAuthorizationPort::class);
    $auth->expects(self::once())->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);
    $store = $this->createMock(MaintenanceRateStorePort::class);
    $store->expects(self::never())->method('list');
    $this->expectException(MaintenanceCostException::class);
    $this->expectExceptionMessage('Missing');
    (new ListMaintenanceRatesHandler($store, new MaintenanceCostAccessGuard($auth), $this->createStub(OrganizationWorkforceDirectoryPort::class)))(new ListMaintenanceRatesQuery(self::ORG, 'actor'));
  }
}
