<?php

declare(strict_types=1);

namespace Tests\Unit\Mission\Application\UseCase\Workflow;

use Mission\Application\Contract\Workflow\{
  MissionWorkflowContext,
  MissionWorkflowMutation,
  MissionWorkflowPage,
  MissionWorkflowView
};
use Mission\Application\Port\Outbound\MissionWorkflowGatewayPort;
use Mission\Application\Service\MissionMemberPolicy;
use Mission\Application\UseCase\Command\Workflow\MutateMissionWorkflow\{
  MutateMissionWorkflowCommand,
  MutateMissionWorkflowHandler
};
use Mission\Application\UseCase\Query\Workflow\GetMissionWorkflow\{
  GetMissionWorkflowHandler,
  GetMissionWorkflowQuery
};
use Mission\Application\UseCase\Query\Workflow\ListMissionWorkflow\{
  ListMissionWorkflowHandler,
  ListMissionWorkflowQuery
};
use Mission\Domain\Exception\MissionAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MissionWorkflowHandlersTest extends TestCase
{
  #[Test]
  public function itDelegatesAReviewedChangeMutationThroughTheWorkflowPort(): void
  {
    $context = new MissionWorkflowContext('mission-1', 'organization-1', 'submitted', 'member-1');
    $view = new MissionWorkflowView('change', 'organization-1', ['id' => 'change-1']);
    $repository = $this->createMock(MissionWorkflowGatewayPort::class);
    $repository->expects(self::once())
      ->method('resourceContext')
      ->with('change', 'change-1')
      ->willReturn($context);
    $repository->expects(self::once())
      ->method('mutate')
      ->with(self::callback(
        static fn (MissionWorkflowMutation $mutation): bool => 'change' === $mutation->resource
        && 'update' === $mutation->action
        && 4 === $mutation->expectedRevision,
      ))
      ->willReturn($view);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('user-1', 'organization-1', 'organization.missions.review')
      ->willReturn(true);

    $result = (new MutateMissionWorkflowHandler($repository, $authorization, $this->memberPolicy()))(
      new MutateMissionWorkflowCommand(
        resource: 'change',
        action: 'update',
        userId: 'user-1',
        id: 'change-1',
        payload: ['status' => 'rejected'],
        expectedRevision: 4,
      ),
    );

    self::assertSame($view, $result->view);
  }

  #[Test]
  public function itRequiresPlanPermissionWhenCreatingAMission(): void
  {
    $repository = $this->createMock(MissionWorkflowGatewayPort::class);
    $repository->expects(self::never())->method('mutate');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('user-1', 'organization-1', 'organization.missions.plan')
      ->willReturn(false);

    $handler = new MutateMissionWorkflowHandler($repository, $authorization, $this->memberPolicy());

    $this->expectException(MissionAccessDeniedException::class);
    $handler(new MutateMissionWorkflowCommand(
      resource: 'mission',
      action: 'create',
      userId: 'user-1',
      id: null,
      payload: ['organizationId' => 'organization-1'],
    ));
  }

  #[Test]
  public function itRejectsExecutionByANonParticipantWithOrganizationPermission(): void
  {
    $context = new MissionWorkflowContext(
      '018f0b68-6758-7a12-8a1d-3f0d97f63c10',
      '018f0b68-6758-7a12-8a1d-3f0d97f63c11',
      'planned',
      '018f0b68-6758-7a12-8a1d-3f0d97f63c12',
    );
    $repository = $this->createMock(MissionWorkflowGatewayPort::class);
    $repository->method('resourceContext')->willReturn($context);
    $repository->expects(self::never())->method('mutate');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $this->expectException(MissionAccessDeniedException::class);

    (new MutateMissionWorkflowHandler($repository, $authorization, $this->memberPolicy()))(
      new MutateMissionWorkflowCommand(
        resource: 'mission',
        action: 'update',
        userId: '018f0b68-6758-7a12-8a1d-3f0d97f63c13',
        id: '018f0b68-6758-7a12-8a1d-3f0d97f63c10',
        payload: ['status' => 'in_progress'],
      ),
    );
  }

  #[Test]
  public function itAuthorizesAndReturnsAWorkflowView(): void
  {
    $view = new MissionWorkflowView('mission', 'organization-1', ['id' => 'mission-1']);
    $repository = $this->createMock(MissionWorkflowGatewayPort::class);
    $repository->expects(self::once())->method('get')->with('mission', 'mission-1')->willReturn($view);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('user-1', 'organization-1', 'organization.missions.read')
      ->willReturn(true);

    $result = (new GetMissionWorkflowHandler($repository, $authorization))(
      new GetMissionWorkflowQuery('user-1', 'mission', 'mission-1'),
    );

    self::assertSame($view, $result->view);
  }

  #[Test]
  public function itAuthorizesAChildCollectionAgainstItsMissionOrganization(): void
  {
    $context = new MissionWorkflowContext('mission-1', 'organization-1', 'in_progress', 'member-1');
    $page = new MissionWorkflowPage([], 1, 30, 0);
    $repository = $this->createMock(MissionWorkflowGatewayPort::class);
    $repository->expects(self::once())->method('missionContext')->with('mission-1')->willReturn($context);
    $repository->expects(self::once())
      ->method('list')
      ->with('work_item', 'mission-1', ['status' => 'planned'], 1, 30)
      ->willReturn($page);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('user-1', 'organization-1', 'organization.missions.read')
      ->willReturn(true);

    $result = (new ListMissionWorkflowHandler($repository, $authorization))(
      new ListMissionWorkflowQuery('user-1', 'work_item', 'mission-1', ['status' => 'planned'], 1, 30),
    );

    self::assertSame($page, $result->page);
  }

  private function memberPolicy(): MissionMemberPolicy
  {
    return new MissionMemberPolicy($this->createStub(OrganizationMemberRepositoryPort::class));
  }
}
