<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Workflow;

use Intervention\Application\Contract\Workflow\{
  InterventionWorkflowContext,
  InterventionWorkflowMutation,
  InterventionWorkflowPage,
  InterventionWorkflowView
};
use Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort;
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Application\UseCase\Command\Workflow\MutateInterventionWorkflow\{
  MutateInterventionWorkflowCommand,
  MutateInterventionWorkflowHandler
};
use Intervention\Application\UseCase\Query\Workflow\GetInterventionWorkflow\{
  GetInterventionWorkflowHandler,
  GetInterventionWorkflowQuery
};
use Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow\{
  ListInterventionWorkflowHandler,
  ListInterventionWorkflowQuery
};
use Intervention\Domain\Exception\InterventionAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InterventionWorkflowHandlersTest extends TestCase
{
  #[Test]
  public function itDelegatesAReviewedChangeMutationThroughTheWorkflowPort(): void
  {
    $context = new InterventionWorkflowContext('intervention-1', 'organization-1', 'submitted', 'member-1');
    $view = new InterventionWorkflowView('change', 'organization-1', ['id' => 'change-1']);
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::once())
      ->method('resourceContext')
      ->with('change', 'change-1')
      ->willReturn($context);
    $repository->expects(self::once())
      ->method('mutate')
      ->with(self::callback(
        static fn (InterventionWorkflowMutation $mutation): bool => 'change' === $mutation->resource
        && 'update' === $mutation->action
        && 4 === $mutation->expectedRevision,
      ))
      ->willReturn($view);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('user-1', 'organization-1', 'organization.interventions.review')
      ->willReturn(true);

    $result = (new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy()))(
      new MutateInterventionWorkflowCommand(
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
  public function itRequiresPlanPermissionWhenCreatingAIntervention(): void
  {
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::never())->method('mutate');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('user-1', 'organization-1', 'organization.interventions.plan')
      ->willReturn(false);

    $handler = new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy());

    $this->expectException(InterventionAccessDeniedException::class);
    $handler(new MutateInterventionWorkflowCommand(
      resource: 'intervention',
      action: 'create',
      userId: 'user-1',
      id: null,
      payload: ['organizationId' => 'organization-1'],
    ));
  }

  #[Test]
  public function itRejectsExecutionByANonParticipantWithOrganizationPermission(): void
  {
    $context = new InterventionWorkflowContext(
      '018f0b68-6758-7a12-8a1d-3f0d97f63c10',
      '018f0b68-6758-7a12-8a1d-3f0d97f63c11',
      'planned',
      '018f0b68-6758-7a12-8a1d-3f0d97f63c12',
    );
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->method('resourceContext')->willReturn($context);
    $repository->expects(self::never())->method('mutate');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $this->expectException(InterventionAccessDeniedException::class);

    (new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy()))(
      new MutateInterventionWorkflowCommand(
        resource: 'intervention',
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
    $view = new InterventionWorkflowView('intervention', 'organization-1', ['id' => 'intervention-1']);
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::once())->method('get')->with('intervention', 'intervention-1')->willReturn($view);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('user-1', 'organization-1', 'organization.interventions.read')
      ->willReturn(true);

    $result = (new GetInterventionWorkflowHandler($repository, $authorization))(
      new GetInterventionWorkflowQuery('user-1', 'intervention', 'intervention-1'),
    );

    self::assertSame($view, $result->view);
  }

  #[Test]
  public function itAuthorizesAChildCollectionAgainstItsInterventionOrganization(): void
  {
    $context = new InterventionWorkflowContext('intervention-1', 'organization-1', 'in_progress', 'member-1');
    $page = new InterventionWorkflowPage([], 1, 30, 0);
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::once())->method('interventionContext')->with('intervention-1')->willReturn($context);
    $repository->expects(self::once())
      ->method('list')
      ->with('work_item', 'intervention-1', ['status' => 'planned'], 1, 30)
      ->willReturn($page);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('user-1', 'organization-1', 'organization.interventions.read')
      ->willReturn(true);

    $result = (new ListInterventionWorkflowHandler($repository, $authorization))(
      new ListInterventionWorkflowQuery('user-1', 'work_item', 'intervention-1', ['status' => 'planned'], 1, 30),
    );

    self::assertSame($page, $result->page);
  }

  private function memberPolicy(): InterventionMemberPolicy
  {
    return new InterventionMemberPolicy($this->createStub(OrganizationMemberRepositoryPort::class));
  }
}
