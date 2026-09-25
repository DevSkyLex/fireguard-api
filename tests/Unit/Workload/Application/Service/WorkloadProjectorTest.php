<?php

declare(strict_types=1);

namespace Tests\Unit\Workload\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Contract\Workload\{InterventionTimeContribution, InterventionWorkContribution};
use Intervention\Application\Port\Inbound\InterventionWorkloadContributionsPort;
use Organization\Application\Contract\Workforce\{OrganizationWorkforceContext, OrganizationWorkforceMember};
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\ClockPort;
use Workload\Application\Contract\Capacity\CapacityWeekView;
use Workload\Application\Port\Outbound\CapacityRepositoryPort;
use Workload\Application\Service\WorkloadProjector;

final class WorkloadProjectorTest extends TestCase
{
  public function testProjectionSeparatesActualRemainingAndUnassignedWorkAndReplacesDemand(): void
  {
    $assigned = new InterventionWorkContribution('assigned', 'intervention', 'Assigned work', 'member', 60, '2026-09-14', '2026-09-14', 'committed', 1);
    $unassigned = new InterventionWorkContribution('unassigned', 'intervention', 'Unassigned work', null, 45, '2026-09-14', '2026-09-14', 'committed', 1);
    $contributions = $this->createMock(InterventionWorkloadContributionsPort::class);
    $contributions->expects(self::exactly(2))->method('tasks')->with('organization', 'UTC')->willReturn([$assigned, $unassigned]);
    $contributions->expects(self::exactly(2))->method('actuals')->with('organization', '2026-09-14', '2026-09-14')->willReturn([
      new InterventionTimeContribution('time', 'assigned', 'member', '2026-09-14', 30, 1, 'intervention', 'Assigned work'),
    ]);
    $workforce = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $workforce->expects(self::exactly(2))->method('context')->with('organization')->willReturn(new OrganizationWorkforceContext('UTC', 'monday'));
    $workforce->expects(self::exactly(2))->method('members')->with('organization')->willReturn([new OrganizationWorkforceMember('member', 'user', true)]);
    $capacities = $this->createMock(CapacityRepositoryPort::class);
    $capacities->expects(self::exactly(2))->method('weeks')->with('organization')->willReturn([new CapacityWeekView('week', 'organization', '2026-09-14', [120, 0, 0, 0, 0, 0, 0])]);
    $capacities->expects(self::exactly(2))->method('exceptions')->with('organization')->willReturn([]);
    $clock = $this->createMock(ClockPort::class);
    $clock->expects(self::exactly(2))->method('now')->willReturn(new DateTimeImmutable('2026-09-14T09:00:00+00:00'));
    $projector = new WorkloadProjector($contributions, $workforce, $capacities, $clock);

    $complete = $projector->project('organization', '2026-09-14', '2026-09-14');
    self::assertSame('partial', $complete->view->completeness);
    self::assertSame('unassigned', $complete->view->unassigned[0]->reason);
    self::assertSame(120, $complete->view->members[0]->days[0]->capacityMinutes);
    self::assertSame(30, $complete->view->members[0]->days[0]->actualMinutes);
    self::assertSame(60, $complete->view->members[0]->days[0]->remainingMinutes);

    $replacement = new InterventionWorkContribution('assigned', 'intervention', 'Assigned work', 'member', 90, '2026-09-14', '2026-09-14', 'committed', 2);
    $scoped = $projector->project('organization', '2026-09-14', '2026-09-14', ['member'], [$replacement]);
    self::assertSame([], $scoped->view->unassigned);
    self::assertSame('complete', $scoped->view->completeness);
    self::assertSame(90, $scoped->view->members[0]->days[0]->remainingMinutes);
    self::assertNotSame($complete->fingerprint, $scoped->fingerprint);
  }
}
