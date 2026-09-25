<?php

declare(strict_types=1);

namespace Tests\Unit\Workload\Application\UseCase\Query\Projection\GetWorkload;

use Organization\Application\Contract\Workforce\{OrganizationWorkforceMember, OrganizationWorkforceMemberProfile};
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort, TeamDirectoryPort};
use PHPUnit\Framework\TestCase;
use Workload\Application\Contract\Projection\{MemberWorkloadView, WorkloadDayView, WorkloadProjectionSnapshot, WorkloadProjectionView};
use Workload\Application\Port\Inbound\WorkloadProjectionPort;
use Workload\Application\UseCase\Query\Projection\GetWorkload\{GetWorkloadHandler, GetWorkloadQuery};

final class GetWorkloadHandlerTest extends TestCase
{
  public function testOverloadFilterPrecedesPaginationAndPreservesMemberOptions(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('isMemberOf')->with('user', 'organization')->willReturn(true);
    $authorization->expects(self::exactly(3))->method('hasPermission')->willReturnCallback(
      static fn (string $userId, string $organizationId, string $permission): bool => 'user' === $userId && 'organization' === $organizationId && 'organization.workload.read' === $permission,
    );
    $workforce = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $workforce->expects(self::once())->method('members')->with('organization')->willReturn([
      new OrganizationWorkforceMember('member-a', 'other', true),
      new OrganizationWorkforceMember('member-b', 'user', true),
    ]);
    $workforce->expects(self::once())->method('profiles')->with('organization', ['member-a', 'member-b'])->willReturn([
      'member-a' => new OrganizationWorkforceMemberProfile('member-a', 'Zoe', null, ['technician']),
      'member-b' => new OrganizationWorkforceMemberProfile('member-b', 'Alice', null, ['manager']),
    ]);
    $workforce->expects(self::once())->method('teams')->with('organization')->willReturn([['id' => 'team', 'name' => 'Team']]);
    $teams = $this->createStub(TeamDirectoryPort::class);
    $day = new WorkloadDayView('2026-09-14', 120, 0, 180, 0, 60, 150.0, 'complete', 'overloaded', []);
    $normalDay = new WorkloadDayView('2026-09-14', 120, 0, 60, 0, 0, 50.0, 'complete', 'available', []);
    $projection = new WorkloadProjectionSnapshot(new WorkloadProjectionView(
      '2026-09-14',
      '2026-09-14',
      '2026-09-14',
      'UTC',
      'monday',
      '2026-09-14T09:00:00+00:00',
      [new MemberWorkloadView('member-a', [$day], []), new MemberWorkloadView('member-b', [$normalDay], [])],
      [],
      'complete',
    ), 'fingerprint');
    $projector = $this->createMock(WorkloadProjectionPort::class);
    $projector->expects(self::once())->method('project')->with('organization', '2026-09-14', '2026-09-14', null)->willReturn($projection);

    $result = new GetWorkloadHandler($authorization, $workforce, $teams, $projector)(new GetWorkloadQuery(
      'user',
      'organization',
      '2026-09-14',
      '2026-09-14',
      overloadedOnly: true,
      page: 2,
      pageSize: 1,
    ));

    self::assertSame(1, $result->totalItems);
    self::assertSame(1, $result->page);
    self::assertSame('member-a', $result->projection->members[0]->memberId);
    self::assertSame('Zoe', $result->projection->members[0]->displayName);
    self::assertSame(['member-b', 'member-a'], [$result->memberOptions[0]['id'], $result->memberOptions[1]['id']]);
    self::assertSame([['id' => 'team', 'name' => 'Team']], $result->teams);
    self::assertTrue($result->canReadTeam);
    self::assertFalse($result->canManageCapacity);
  }
}
