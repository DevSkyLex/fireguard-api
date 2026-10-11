<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use Intervention\Application\Contract\Time\TimeEntryTaskContext;
use Intervention\Application\Contract\Workflow\{InterventionWorkflowContext, InterventionWorkflowView};
use Intervention\Application\Port\Outbound\Internal\InterventionTimeEntryRepositoryPort;
use Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort;
use Intervention\Application\Service\{InterventionTimeAccessPolicy, InterventionWorkItemCapabilities};
use Organization\Application\Contract\Workforce\OrganizationWorkforceMember;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function array_keys;

/**
 * Class InterventionWorkItemCapabilitiesTest
 *
 * Verifies that grouped task capabilities preserve independent time rights and current assignment boundaries.
 *
 * @category Test
 */
#[CoversClass(InterventionWorkItemCapabilities::class)]
final class InterventionWorkItemCapabilitiesTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGroupedActionsPreserveHistoricalTimeRightsAndParentPhaseWithBoundedReads(): void
  {
    $contexts = [
      'assigned' => new TimeEntryTaskContext('assigned', 'planned', 'org', 'actor', 'other', [], []),
      'previous' => new TimeEntryTaskContext('previous', 'planned', 'org', 'other', 'actor', ['actor'], ['actor']),
      'participant' => new TimeEntryTaskContext('participant', 'planned', 'org', null, 'other', ['actor'], []),
      'responsible' => new TimeEntryTaskContext('responsible', 'planned', 'org', null, 'actor', [], []),
      'outsider' => new TimeEntryTaskContext('outsider', 'planned', 'org', 'other', 'actor', ['actor'], []),
      'draft' => new TimeEntryTaskContext('draft', 'draft', 'org', 'other', 'other', [], []),
      'published' => new TimeEntryTaskContext('published', 'published', 'org', 'actor', 'actor', [], []),
      'completed' => new TimeEntryTaskContext('completed', 'planned', 'org', 'actor', 'actor', [], []),
      'skipped' => new TimeEntryTaskContext('skipped', 'planned', 'org', 'actor', 'actor', [], []),
      'missing-a' => new TimeEntryTaskContext('missing-a', 'missing', 'org', 'actor', 'actor', [], []),
      'missing-b' => new TimeEntryTaskContext('missing-b', 'missing', 'org', 'actor', 'actor', [], []),
    ];
    $tasks = $this->createMock(InterventionTimeEntryRepositoryPort::class);
    $tasks->expects(self::once())->method('contexts')->with(array_keys($contexts))->willReturn($contexts);
    $tasks->expects(self::never())->method('context');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('isMemberOf')->with('caller', 'org')->willReturn(true);
    $authorization->expects(self::exactly(4))->method('hasPermission')->with('caller', 'org', self::anything())
      ->willReturnCallback(static fn (string $user, string $organization, string $permission): bool => 'organization.interventions.time.manage' !== $permission);
    $workforce = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $workforce->expects(self::once())->method('members')->with('org')->willReturn([
      new OrganizationWorkforceMember('inactive', 'caller', false),
      new OrganizationWorkforceMember('other', 'other-user', true),
      new OrganizationWorkforceMember('actor', 'caller', true),
      new OrganizationWorkforceMember('later-identity', 'caller', true),
    ]);
    $workflow = $this->createMock(InterventionWorkflowGatewayPort::class);
    $workflow->expects(self::exactly(4))->method('interventionContext')->willReturnCallback(static fn (string $id): ?InterventionWorkflowContext => match ($id) {
      'planned' => new InterventionWorkflowContext($id, 'org', 'planned', 'actor'),
      'draft' => new InterventionWorkflowContext($id, 'org', 'draft', 'actor'),
      'published' => new InterventionWorkflowContext($id, 'org', 'published', 'actor'),
      default => null,
    });
    $views = [];
    foreach (array_keys($contexts) as $id) {
      $views[] = new InterventionWorkflowView('work_item', 'org', ['id' => $id, 'status' => match ($id) {
        'completed', 'skipped' => $id,
        default => 'pending',
      }]);
    }

    $result = $this->capabilities($tasks, $workflow, $authorization, $workforce)->forViews($views, 'caller');

    self::assertSame(array_keys($contexts), array_keys($result));
    self::assertSame($this->actions(true, true, true, true), $result['assigned']);
    self::assertSame($this->actions(true, false, true, false), $result['previous']);
    self::assertSame($this->actions(true, true, true, true), $result['participant']);
    self::assertSame($this->actions(true, true, true, true), $result['responsible']);
    self::assertSame($this->actions(false, false, true, false), $result['outsider']);
    self::assertSame($this->actions(false, true, true, false), $result['draft']);
    self::assertSame($this->actions(true, false, false, false), $result['published']);
    self::assertSame($this->actions(true, false, false, true), $result['completed']);
    self::assertSame($this->actions(true, false, false, true), $result['skipped']);
    self::assertSame($this->actions(true, false, false, false), $result['missing-a']);
    self::assertSame($result['missing-a'], $result['missing-b']);
  }

  #[Test]
  #[DataProvider('unresolvedActorCases')]
  public function testNoCapabilitySurvivesAnUnresolvedCurrentActor(bool $isMember, bool $active, bool $withWorkforce): void
  {
    $tasks = $this->createStub(InterventionTimeEntryRepositoryPort::class);
    $tasks->method('contexts')->willReturn(['task' => new TimeEntryTaskContext('task', 'parent', 'org', 'actor', 'actor', ['actor'], ['actor'])]);
    $workflow = $this->createStub(InterventionWorkflowGatewayPort::class);
    $workflow->method('interventionContext')->willReturn(new InterventionWorkflowContext('parent', 'org', 'planned', 'actor'));
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('isMemberOf')->with('caller', 'org')->willReturn($isMember);
    $authorization->expects(self::never())->method('hasPermission');
    $workforce = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $workforce->expects($isMember && $withWorkforce ? self::once() : self::never())->method('members')
      ->willReturn([new OrganizationWorkforceMember('actor', 'caller', $active)]);
    $capabilities = new InterventionWorkItemCapabilities($tasks, $workflow, new InterventionTimeAccessPolicy($authorization, $workforce), $authorization, $withWorkforce ? $workforce : null);

    $result = $capabilities->forViews([new InterventionWorkflowView('work_item', 'org', ['id' => 'task', 'status' => 'pending'])], 'caller');

    self::assertSame($this->actions(false, false, false, false), $result['task']);
  }

  /**
   * @return iterable<string, array{bool, bool, bool}>
   */
  public static function unresolvedActorCases(): iterable
  {
    yield 'not an organization member' => [false, true, true];
    yield 'inactive workforce identity' => [true, false, true];
    yield 'workforce directory unavailable' => [true, true, false];
  }

  #[Test]
  public function testTimeManagementDoesNotGrantExecutionAndTaskStatusesUseTheLastRepeatedView(): void
  {
    $tasks = $this->createMock(InterventionTimeEntryRepositoryPort::class);
    $tasks->expects(self::once())->method('contexts')->with(['task', 'task'])
      ->willReturn(['task' => new TimeEntryTaskContext('task', 'parent', 'org', 'other', 'other', [], [])]);
    $workflow = $this->createStub(InterventionWorkflowGatewayPort::class);
    $workflow->method('interventionContext')->willReturn(new InterventionWorkflowContext('parent', 'org', 'planned', 'other'));
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturnCallback(static fn (string $user, string $organization, string $permission): bool => 'organization.interventions.time.manage' === $permission || 'organization.interventions.plan' === $permission);
    $workforce = $this->createStub(OrganizationWorkforceDirectoryPort::class);
    $workforce->method('members')->willReturn([new OrganizationWorkforceMember('actor', 'caller', true)]);

    $result = $this->capabilities($tasks, $workflow, $authorization, $workforce)->forViews([
      new InterventionWorkflowView('work_item', 'org', ['id' => null, 'status' => 'pending']),
      new InterventionWorkflowView('work_item', 'org', ['id' => 'task', 'status' => 'pending']),
      new InterventionWorkflowView('work_item', 'org', ['id' => 'task', 'status' => 'completed']),
    ], 'caller');

    self::assertSame(['task' => [...$this->actions(true, false, false, false), 'canManageTime' => true]], $result);
  }

  /**
   * @return array{canLogTime: bool, canManageTime: bool, canReestimate: bool, canReassign: bool, canEditPlanning: bool, canExecute: bool}
   */
  private function actions(bool $log, bool $reestimate, bool $planning, bool $execute): array
  {
    return ['canLogTime' => $log, 'canManageTime' => false, 'canReestimate' => $reestimate, 'canReassign' => $planning, 'canEditPlanning' => $planning, 'canExecute' => $execute];
  }

  private function capabilities(InterventionTimeEntryRepositoryPort $tasks, InterventionWorkflowGatewayPort $workflow, OrganizationAuthorizationPort $authorization, OrganizationWorkforceDirectoryPort $workforce): InterventionWorkItemCapabilities
  {
    return new InterventionWorkItemCapabilities($tasks, $workflow, new InterventionTimeAccessPolicy($authorization, $workforce), $authorization, $workforce);
  }
  // #endregion
}
