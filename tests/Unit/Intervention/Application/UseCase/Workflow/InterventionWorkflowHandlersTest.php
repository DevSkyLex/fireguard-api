<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Workflow;

use DateTimeImmutable;
use Intervention\Application\Contract\Workflow\{
  InterventionWorkflowContext,
  InterventionWorkflowMutation,
  InterventionWorkflowPage,
  InterventionWorkflowView
};
use Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort;
use Intervention\Application\Service\{InterventionActionPolicy, InterventionMemberPolicy};
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
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Intervention\Domain\Service\{InterventionChangePolicy, InterventionMutabilityPolicy, InterventionTransitionPolicy};
use Intervention\Domain\ValueObject\InterventionStatus;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\ClockPort;

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
      ->method('resolveAccess')
      ->with('user-1', 'organization-1', 'organization.interventions.review')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $result = (new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy(), $this->actionPolicy()))(
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
      ->method('resolveAccess')
      ->with('user-1', 'organization-1', 'organization.interventions.plan')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $handler = new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy(), $this->actionPolicy());

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
  public function itReportsAnOutOfScopeInterventionCreationAsNotFound(): void
  {
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::never())->method('mutate');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with('user-1', 'organization-1', 'organization.interventions.plan')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $handler = new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy(), $this->actionPolicy());

    $this->expectException(InterventionNotFoundException::class);
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
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $this->expectException(InterventionAccessDeniedException::class);

    (new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy(), $this->actionPolicy()))(
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
      ->method('resolveAccess')
      ->with('user-1', 'organization-1', 'organization.interventions.read')
      ->willReturn(OrganizationAccessDecision::GRANTED);

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
      ->method('resolveAccess')
      ->with('user-1', 'organization-1', 'organization.interventions.read')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $result = (new ListInterventionWorkflowHandler($repository, $authorization, $this->createStub(ClockPort::class)))(
      new ListInterventionWorkflowQuery('user-1', 'work_item', 'intervention-1', ['status' => 'planned'], 1, 30),
    );

    self::assertSame($page, $result->page);
  }

  #[Test]
  public function itReportsAMissingWorkflowResourceAsNotFound(): void
  {
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::once())->method('get')->with('change', 'change-1')->willReturn(null);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('resolveAccess');

    $this->expectException(InterventionNotFoundException::class);

    (new GetInterventionWorkflowHandler($repository, $authorization))(
      new GetInterventionWorkflowQuery('user-1', 'change', 'change-1'),
    );
  }

  #[Test]
  public function itRefusesToReadAWorkflowViewWithoutTheReadPermission(): void
  {
    $view = new InterventionWorkflowView('intervention', 'organization-1', ['id' => 'intervention-1']);
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::once())->method('get')->willReturn($view);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(InterventionAccessDeniedException::class);

    (new GetInterventionWorkflowHandler($repository, $authorization))(
      new GetInterventionWorkflowQuery('user-1', 'intervention', 'intervention-1'),
    );
  }

  #[Test]
  public function itReportsAnOutOfScopeWorkflowViewAsNotFound(): void
  {
    $view = new InterventionWorkflowView('change', 'organization-1', ['id' => 'change-1']);
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::once())->method('get')->willReturn($view);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    // Byte-for-byte what itReportsAMissingWorkflowResourceAsNotFound asserts:
    // reading the record to learn who owns it must not become a way for an
    // outsider to confirm the record is there.
    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage('Intervention with ID "change-1" not found.');

    (new GetInterventionWorkflowHandler($repository, $authorization))(
      new GetInterventionWorkflowQuery('user-1', 'change', 'change-1'),
    );
  }

  #[Test]
  public function itReportsAMissingParentInterventionAsNotFoundWhenListingAChildCollection(): void
  {
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::once())->method('interventionContext')->with('intervention-1')->willReturn(null);
    $repository->expects(self::never())->method('list');

    $this->expectException(InterventionNotFoundException::class);

    (new ListInterventionWorkflowHandler(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->createStub(ClockPort::class),
    ))(
      new ListInterventionWorkflowQuery('user-1', 'work_item', 'intervention-1', [], 1, 30),
    );
  }

  #[Test]
  public function itRefusesToListAWorkflowCollectionWithoutTheReadPermission(): void
  {
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::never())->method('interventionContext');
    $repository->expects(self::never())->method('list');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(InterventionAccessDeniedException::class);

    (new ListInterventionWorkflowHandler($repository, $authorization, $this->createStub(ClockPort::class)))(
      new ListInterventionWorkflowQuery('user-1', 'intervention', 'organization-1', [], 1, 30),
    );
  }

  #[Test]
  public function itReportsAnOutOfScopeInterventionResourceScopeAsNotFound(): void
  {
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::never())->method('interventionContext');
    $repository->expects(self::never())->method('list');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with('user-1', 'organization-1', 'organization.interventions.read')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(InterventionNotFoundException::class);

    (new ListInterventionWorkflowHandler($repository, $authorization, $this->createStub(ClockPort::class)))(
      new ListInterventionWorkflowQuery('user-1', 'intervention', 'organization-1', [], 1, 30),
    );
  }

  #[Test]
  public function itReportsAnOutOfScopeChildCollectionAsNotFound(): void
  {
    $context = new InterventionWorkflowContext('intervention-1', 'organization-1', 'in_progress', 'member-1');
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::once())->method('interventionContext')->with('intervention-1')->willReturn($context);
    $repository->expects(self::never())->method('list');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with('user-1', 'organization-1', 'organization.interventions.read')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(InterventionNotFoundException::class);

    (new ListInterventionWorkflowHandler($repository, $authorization, $this->createStub(ClockPort::class)))(
      new ListInterventionWorkflowQuery('user-1', 'work_item', 'intervention-1', [], 1, 30),
    );
  }

  #[Test]
  public function itTranslatesTheOverdueFilterIntoTheGatewaysResolvedFilters(): void
  {
    $now = new DateTimeImmutable('2026-08-18T10:00:00+00:00');
    $page = new InterventionWorkflowPage([], 1, 30, 0);
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::once())
      ->method('list')
      ->with(
        'intervention',
        'organization-1',
        [
          'overdueAsOf' => $now,
          'overdueExcludedStatuses' => InterventionStatus::closedValues(),
        ],
        1,
        30,
      )
      ->willReturn($page);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    /** @var ClockPort&MockObject $clock */
    $clock = $this->createMock(ClockPort::class);
    $clock->expects(self::once())->method('now')->willReturn($now);

    $result = (new ListInterventionWorkflowHandler($repository, $authorization, $clock))(
      new ListInterventionWorkflowQuery('user-1', 'intervention', 'organization-1', ['due' => 'overdue'], 1, 30),
    );

    self::assertSame($page, $result->page);
  }

  #[Test]
  public function itKeepsTheOverdueFilterComposableWithTheCallerSuppliedDueAtBounds(): void
  {
    $now = new DateTimeImmutable('2026-08-18T10:00:00+00:00');
    $page = new InterventionWorkflowPage([], 1, 30, 0);
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::once())
      ->method('list')
      ->with(
        'intervention',
        'organization-1',
        [
          'dueAtAfter' => '2026-08-01T00:00:00Z',
          'overdueAsOf' => $now,
          'overdueExcludedStatuses' => InterventionStatus::closedValues(),
        ],
        1,
        30,
      )
      ->willReturn($page);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);

    (new ListInterventionWorkflowHandler($repository, $authorization, $clock))(
      new ListInterventionWorkflowQuery(
        'user-1',
        'intervention',
        'organization-1',
        ['dueAtAfter' => '2026-08-01T00:00:00Z', 'due' => 'overdue'],
        1,
        30,
      ),
    );

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itNeverConsultsTheClockWithoutTheOverdueFilter(): void
  {
    $page = new InterventionWorkflowPage([], 1, 30, 0);
    $repository = $this->createStub(InterventionWorkflowGatewayPort::class);
    $repository->method('list')->willReturn($page);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    /** @var ClockPort&MockObject $clock */
    $clock = $this->createMock(ClockPort::class);
    $clock->expects(self::never())->method('now');

    (new ListInterventionWorkflowHandler($repository, $authorization, $clock))(
      new ListInterventionWorkflowQuery('user-1', 'intervention', 'organization-1', ['status' => 'planned'], 1, 30),
    );

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itRejectsInterventionCreationWithoutAnOrganizationIdInThePayload(): void
  {
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::never())->method('mutate');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('resolveAccess');

    $handler = new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy(), $this->actionPolicy());

    $this->expectException(InterventionNotFoundException::class);
    $handler(new MutateInterventionWorkflowCommand(
      resource: 'intervention',
      action: 'create',
      userId: 'user-1',
      id: null,
      payload: ['organizationId' => ''],
    ));
  }

  #[Test]
  public function itResolvesTheParentInterventionContextWhenCreatingAChildResource(): void
  {
    $context = new InterventionWorkflowContext('intervention-1', 'organization-1', 'draft', 'member-1');
    $view = new InterventionWorkflowView('work_item', 'organization-1', ['id' => 'work-item-1']);
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::once())->method('interventionContext')->with('intervention-1')->willReturn($context);
    $repository->expects(self::never())->method('resourceContext');
    $repository->expects(self::once())->method('mutate')->willReturn($view);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with('user-1', 'organization-1', 'organization.interventions.plan')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $result = (new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy(), $this->actionPolicy()))(
      new MutateInterventionWorkflowCommand(
        resource: 'work_item',
        action: 'create',
        userId: 'user-1',
        id: null,
        payload: ['interventionId' => 'intervention-1'],
      ),
    );

    self::assertSame($view, $result->view);
  }

  #[Test]
  public function itReportsAnOutOfScopeChildResourceCreationAsNotFound(): void
  {
    $context = new InterventionWorkflowContext('intervention-1', 'organization-1', 'draft', 'member-1');
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::once())->method('interventionContext')->with('intervention-1')->willReturn($context);
    $repository->expects(self::never())->method('mutate');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with('user-1', 'organization-1', 'organization.interventions.plan')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(InterventionNotFoundException::class);

    (new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy(), $this->actionPolicy()))(
      new MutateInterventionWorkflowCommand(
        resource: 'work_item',
        action: 'create',
        userId: 'user-1',
        id: null,
        payload: ['interventionId' => 'intervention-1'],
      ),
    );
  }

  #[Test]
  public function itReportsAMissingParentInterventionIdAsNotFoundWhenCreatingAChildResource(): void
  {
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::never())->method('interventionContext');
    $repository->expects(self::never())->method('mutate');

    $handler = new MutateInterventionWorkflowHandler(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->memberPolicy(),
      $this->actionPolicy(),
    );

    $this->expectException(InterventionNotFoundException::class);
    $handler(new MutateInterventionWorkflowCommand(
      resource: 'work_item',
      action: 'create',
      userId: 'user-1',
      id: null,
      payload: [],
    ));
  }

  #[Test]
  public function itReportsAMissingResourceContextAsNotFoundWhenTheCommandCarriesNoIdentifier(): void
  {
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::never())->method('resourceContext');
    $repository->expects(self::never())->method('mutate');

    $handler = new MutateInterventionWorkflowHandler(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->memberPolicy(),
      $this->actionPolicy(),
    );

    $this->expectException(InterventionNotFoundException::class);
    $handler(new MutateInterventionWorkflowCommand(
      resource: 'change',
      action: 'update',
      userId: 'user-1',
      id: null,
      payload: [],
    ));
  }

  #[Test]
  public function itReportsAnOutOfScopeExistingResourceMutationAsNotFound(): void
  {
    $context = new InterventionWorkflowContext('intervention-1', 'organization-1', 'planned', 'member-1');
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->expects(self::once())->method('resourceContext')->with('intervention', 'intervention-1')->willReturn($context);
    $repository->expects(self::never())->method('mutate');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with('user-1', 'organization-1', 'organization.interventions.execute')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(InterventionNotFoundException::class);

    (new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy(), $this->actionPolicy()))(
      new MutateInterventionWorkflowCommand(
        resource: 'intervention',
        action: 'update',
        userId: 'user-1',
        id: 'intervention-1',
        payload: ['status' => 'in_progress'],
      ),
    );
  }

  /**
   * @return iterable<string, array{array<string, mixed>, string, string}>
   */
  public static function interventionPermissionProvider(): iterable
  {
    yield 'planning a draft' => [['status' => 'planned'], 'draft', 'organization.interventions.plan'];
    yield 'submitting for review' => [['status' => 'submitted'], 'planned', 'organization.interventions.execute'];
    yield 'requesting changes' => [['status' => 'changes_requested'], 'submitted', 'organization.interventions.review'];
    yield 'an unknown target status' => [['status' => 'archived'], 'planned', 'organization.interventions.plan'];
    yield 'abandoning a draft' => [['status' => 'abandoned'], 'draft', 'organization.interventions.plan'];
    yield 'abandoning a change request' => [['status' => 'abandoned'], 'changes_requested', 'organization.interventions.review'];
    yield 'abandoning an in-progress intervention' => [['status' => 'abandoned'], 'in_progress', 'organization.interventions.execute'];
    yield 'a non-status edit on a draft' => [['name' => 'Renamed'], 'draft', 'organization.interventions.plan'];
    yield 'a non-status edit on a planned intervention' => [['name' => 'Renamed'], 'planned', 'organization.interventions.execute'];
    yield 'a non-string status falls back to the lifecycle default' => [['status' => 42], 'planned', 'organization.interventions.execute'];
  }

  /**
   * @param array<string, mixed> $payload
   */
  #[Test]
  #[DataProvider('interventionPermissionProvider')]
  public function itMapsAnInterventionMutationToThePermissionItsTargetStatusRequires(
    array $payload,
    string $currentStatus,
    string $expectedPermission,
  ): void {
    $context = new InterventionWorkflowContext('intervention-1', 'organization-1', $currentStatus, 'member-1');
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->method('resourceContext')->willReturn($context);
    $repository->expects(self::never())->method('mutate');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with('user-1', 'organization-1', $expectedPermission)
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $handler = new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy(), $this->actionPolicy());

    $this->expectException(InterventionAccessDeniedException::class);
    $handler(new MutateInterventionWorkflowCommand(
      resource: 'intervention',
      action: 'update',
      userId: 'user-1',
      id: 'intervention-1',
      payload: $payload,
    ));
  }

  #[Test]
  public function itLetsAPurePlanningPayloadOnANonDraftRequireOnlyThePlanPermission(): void
  {
    $context = new InterventionWorkflowContext('intervention-1', 'organization-1', 'planned', 'member-1');
    $repository = $this->createStub(InterventionWorkflowGatewayPort::class);
    $repository->method('resourceContext')->willReturn($context);
    $repository->method('mutate')->willReturn(new InterventionWorkflowView('intervention', 'organization-1', []));
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    // Plan only — no execute, so the responsible/participant member guard must
    // not run: a planner who is neither may still reschedule.
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with('user-1', 'organization-1', 'organization.interventions.plan')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $handler = new MutateInterventionWorkflowHandler(
      $repository,
      $authorization,
      $this->rejectingMemberPolicy(),
      $this->actionPolicy(),
    );

    $handler(new MutateInterventionWorkflowCommand(
      resource: 'intervention',
      action: 'update',
      userId: 'user-1',
      id: 'intervention-1',
      payload: ['dueAt' => '2026-08-20T18:00:00Z', 'priority' => 'urgent'],
    ));

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itRequiresBothPermissionsWhenAPayloadMixesReplanAndTransition(): void
  {
    $context = new InterventionWorkflowContext('intervention-1', 'organization-1', 'planned', 'member-1');
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->method('resourceContext')->willReturn($context);
    $repository->expects(self::never())->method('mutate');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $checked = [];
    $authorization->method('resolveAccess')->willReturnCallback(
      static function (string $userId, string $organizationId, string $permission) use (&$checked): OrganizationAccessDecision {
        $checked[] = $permission;

        return 'organization.interventions.execute' === $permission
          ? OrganizationAccessDecision::GRANTED
          : OrganizationAccessDecision::MISSING_PERMISSION;
      },
    );

    $handler = new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy(), $this->actionPolicy());

    try {
      $handler(new MutateInterventionWorkflowCommand(
        resource: 'intervention',
        action: 'update',
        userId: 'user-1',
        id: 'intervention-1',
        payload: ['dueAt' => '2026-08-20T18:00:00Z', 'status' => 'in_progress'],
      ));
      self::fail('The missing plan permission must deny the mixed payload.');
    } catch (InterventionAccessDeniedException) {
      // The mixed payload demanded both permissions and failed on plan.
    }

    self::assertContains('organization.interventions.execute', $checked);
    self::assertContains('organization.interventions.plan', $checked);
  }

  /**
   * @return iterable<string, array{string, string, string}>
   */
  public static function childResourcePermissionProvider(): iterable
  {
    yield 'a work item on a draft' => ['work_item', 'draft', 'organization.interventions.plan'];
    yield 'a work item on a planned intervention' => ['work_item', 'planned', 'organization.interventions.execute'];
    yield 'a change on a submitted intervention' => ['change', 'submitted', 'organization.interventions.review'];
    yield 'a change on an in-progress intervention' => ['change', 'in_progress', 'organization.interventions.execute'];
  }

  #[Test]
  #[DataProvider('childResourcePermissionProvider')]
  public function itMapsAChildResourceMutationToThePermissionTheParentStatusRequires(
    string $resource,
    string $currentStatus,
    string $expectedPermission,
  ): void {
    $context = new InterventionWorkflowContext('intervention-1', 'organization-1', $currentStatus, 'member-1');
    $repository = $this->createMock(InterventionWorkflowGatewayPort::class);
    $repository->method('resourceContext')->willReturn($context);
    $repository->expects(self::never())->method('mutate');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with('user-1', 'organization-1', $expectedPermission)
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $handler = new MutateInterventionWorkflowHandler($repository, $authorization, $this->memberPolicy(), $this->actionPolicy());

    $this->expectException(InterventionAccessDeniedException::class);
    $handler(new MutateInterventionWorkflowCommand(
      resource: $resource,
      action: 'update',
      userId: 'user-1',
      id: 'resource-1',
      payload: [],
    ));
  }

  private function memberPolicy(): InterventionMemberPolicy
  {
    return new InterventionMemberPolicy($this->createStub(OrganizationMemberRepositoryPort::class));
  }

  /**
   * The shared {@see InterventionActionPolicy}, wired with inert collaborators:
   * `requiredPermissions()` — the only method the handler calls — is pure and
   * never touches any of them.
   */
  private function actionPolicy(): InterventionActionPolicy
  {
    return new InterventionActionPolicy(
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->memberPolicy(),
      new InterventionTransitionPolicy(),
      new InterventionMutabilityPolicy(),
      new InterventionChangePolicy(),
    );
  }

  /**
   * A member policy whose execute guard always throws — proving, when a test
   * completes anyway, that the guard was never consulted.
   */
  private function rejectingMemberPolicy(): InterventionMemberPolicy
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationAndUser')->willReturn(null);

    return new InterventionMemberPolicy($members);
  }
}
