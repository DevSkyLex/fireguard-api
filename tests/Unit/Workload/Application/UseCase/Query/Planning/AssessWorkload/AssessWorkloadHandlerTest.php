<?php

declare(strict_types=1);

namespace Tests\Unit\Workload\Application\UseCase\Query\Planning\AssessWorkload;

use Intervention\Application\Contract\Workload\InterventionWorkContribution;
use Intervention\Application\Port\Inbound\InterventionWorkloadContributionsPort;
use Organization\Application\Contract\Workforce\{OrganizationWorkforceContext, OrganizationWorkforceMember};
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use PHPUnit\Framework\TestCase;
use Workload\Application\Contract\Planning\{WorkloadAssessment, WorkloadPlanningSnapshot, WorkloadTaskProposal};
use Workload\Application\Contract\Projection\{WorkloadProjectionSnapshot, WorkloadProjectionView};
use Workload\Application\Port\Inbound\WorkloadPlanningPort;
use Workload\Application\UseCase\Query\Planning\AssessWorkload\{AssessWorkloadHandler, AssessWorkloadQuery};

use function count;

final class AssessWorkloadHandlerTest extends TestCase
{
  public function testDraftReplacementKeepsTheFullParentMemberScope(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('isMemberOf')->with('user', 'organization')->willReturn(true);
    $authorization->expects(self::once())->method('hasPermission')->with('user', 'organization', 'organization.interventions.plan')->willReturn(true);
    $workforce = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $workforce->expects(self::once())->method('context')->with('organization')->willReturn(new OrganizationWorkforceContext('UTC', 'monday'));
    $workforce->expects(self::once())->method('members')->with('organization')->willReturn([
      new OrganizationWorkforceMember('old', 'user', true),
      new OrganizationWorkforceMember('new', 'other', true),
    ]);
    $task = new InterventionWorkContribution('task', 'draft', 'Work', 'old', 60, '2026-09-14', '2026-09-14', 'draft', 3);
    $contributions = $this->createMock(InterventionWorkloadContributionsPort::class);
    $contributions->expects(self::once())->method('tasks')->with('organization', 'UTC')->willReturn([$task]);
    $projection = new WorkloadProjectionSnapshot(new WorkloadProjectionView('2026-09-14', '2026-09-14', '2026-09-14', 'UTC', 'monday', '2026-09-14T09:00:00+00:00', [], [], 'complete'), 'fingerprint');
    $before = new WorkloadPlanningSnapshot('organization', ['old', 'old', 'new', 'old'], $projection);
    $assessment = new WorkloadAssessment(false, [], 'complete', 'consent');
    $planning = $this->createMock(WorkloadPlanningPort::class);
    $planning->expects(self::once())->method('capture')->with('organization', ['old', 'old', 'new', 'old'])->willReturn($before);
    $planning->expects(self::once())->method('assess')->with($before, self::callback(static function (array $replacements): bool {
      return 1 === count($replacements)
        && $replacements[0] instanceof InterventionWorkContribution
        && 'task' === $replacements[0]->taskId
        && 'new' === $replacements[0]->memberId
        && 90 === $replacements[0]->remainingMinutes
        && 'committed' === $replacements[0]->commitment
        && 3 === $replacements[0]->revision;
    }))->willReturn($assessment);

    $result = new AssessWorkloadHandler($authorization, $workforce, $contributions, $planning)(new AssessWorkloadQuery(
      'user',
      'organization',
      [new WorkloadTaskProposal('task', 'new', 90, '2026-09-14', '2026-09-14')],
      'draft',
    ));

    self::assertSame($assessment, $result->assessment);
  }
}
