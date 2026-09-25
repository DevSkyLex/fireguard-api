<?php

declare(strict_types=1);

namespace Tests\Unit\Workload\Application\UseCase\Command\Capacity\ChangeCapacity;

use Organization\Application\Contract\Workforce\OrganizationWorkforceMember;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{TransactionManagerPort, UuidGeneratorPort};
use Workload\Application\Contract\Capacity\CapacityWeekView;
use Workload\Application\Port\Inbound\WorkloadCoordinationPort;
use Workload\Application\Port\Outbound\CapacityRepositoryPort;
use Workload\Application\UseCase\Command\Capacity\ChangeCapacity\{ChangeCapacityCommand, ChangeCapacityHandler};

final class ChangeCapacityHandlerTest extends TestCase
{
  public function testOrganizationWeekIsValidatedAndPersistedUnderExclusiveCoordination(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('isMemberOf')->with('user', 'organization')->willReturn(true);
    $authorization->expects(self::once())->method('hasPermission')->with('user', 'organization', 'organization.workload.manage')->willReturn(true);
    $workforce = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $workforce->expects(self::once())->method('members')->with('organization')->willReturn([new OrganizationWorkforceMember('actor', 'user', true)]);
    $capacities = $this->createMock(CapacityRepositoryPort::class);
    $capacities->expects(self::once())->method('weeks')->with('organization')->willReturn([]);
    $capacities->expects(self::once())->method('exceptions')->with('organization')->willReturn([]);
    $capacities->expects(self::once())->method('addWeek')->with('organization', self::callback(static function (CapacityWeekView $week): bool {
      return 'week-id' === $week->id
        && 'organization' === $week->scopeId
        && '2026-09-14' === $week->effectiveOn
        && [120, 120, 120, 120, 120, 0, 0] === $week->minutes;
    }), 'actor');
    $coordination = $this->createMock(WorkloadCoordinationPort::class);
    $coordination->expects(self::once())->method('acquire')->with('organization', [], true);
    $transactions = $this->createMock(TransactionManagerPort::class);
    $transactions->expects(self::once())->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());
    $uuids = $this->createMock(UuidGeneratorPort::class);
    $uuids->expects(self::once())->method('generate')->willReturn('week-id');

    $result = new ChangeCapacityHandler($authorization, $workforce, $capacities, $coordination, $transactions, $uuids)(new ChangeCapacityCommand(
      'user',
      'organization',
      'week',
      effectiveOn: '2026-09-14',
      weekMinutes: [120, 120, 120, 120, 120, 0, 0],
    ));

    self::assertSame('week-id', $result->id);
  }
}
