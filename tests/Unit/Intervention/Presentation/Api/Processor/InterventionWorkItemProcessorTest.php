<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\{Delete, Patch, Post, Put};
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Application\UseCase\Command\Workflow\MutateInterventionWorkflow\{
  MutateInterventionWorkflowCommand,
  MutateInterventionWorkflowResult
};
use Intervention\Domain\Exception\InterventionNotFoundException;
use Intervention\Presentation\Api\Dto\Input\{
  CreateInterventionWorkItemInput,
  UpdateInterventionWorkItemInput
};
use Intervention\Presentation\Api\Factory\InterventionWorkItemOutputFactory;
use Intervention\Presentation\Api\Processor\InterventionWorkItemProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Presentation\Api\Http\{CreationPreconditionGuard, MergePatchFields, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

/**
 * Test InterventionWorkItemProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionWorkItemProcessor::class)]
final class InterventionWorkItemProcessorTest extends TestCase
{
  // #region Constants
  private const string WORK_ITEM_ID = '550e8400-e29b-41d4-a716-446655441502';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655441501';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441503';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441401';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessCreatesAWorkItemFromTheCreateInput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MutateInterventionWorkflowCommand $command): bool => 'work_item' === $command->resource
        && 'create' === $command->action
        && self::USER_ID === $command->userId
        && self::INTERVENTION_ID === $command->payload['interventionId']
        && 'inspection' === $command->payload['action']
        && '/api/equipment/equip-1' === $command->payload['target']
        && self::MEMBER_ID === $command->payload['assigneeId']
        && 'planned' === $command->payload['source']
        && false === $command->payload['required']))
      ->willReturn(new MutateInterventionWorkflowResult($this->view()));

    $input = new CreateInterventionWorkItemInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;
    $input->action = 'inspection';
    $input->target = '/api/equipment/equip-1';
    $input->assignee = '/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::MEMBER_ID;
    $input->required = false;

    $output = $this->createProcessor($commandBus, 'POST')->process($input, new Post());

    self::assertNotNull($output);
    self::assertSame(self::WORK_ITEM_ID, $output->id);
    self::assertSame('pending', $output->status);
  }

  #[Test]
  public function testProcessLeavesTheAssigneeNullWhenTheInputOmitsIt(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MutateInterventionWorkflowCommand $command): bool => null === $command->payload['assigneeId']))
      ->willReturn(new MutateInterventionWorkflowResult($this->view()));

    $input = new CreateInterventionWorkItemInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;

    $this->createProcessor($commandBus, 'POST')->process($input, new Post());
  }

  #[Test]
  public function testAPutRequestIsACreateOnlyMutation(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MutateInterventionWorkflowCommand $command): bool => 'create' === $command->action
        && true === $command->createOnly))
      ->willReturn(new MutateInterventionWorkflowResult($this->view()));

    $input = new CreateInterventionWorkItemInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;

    $this->createProcessor($commandBus, 'PUT', ifNoneMatch: '*')
      ->process($input, new Put(), ['id' => self::WORK_ITEM_ID]);
  }

  #[Test]
  public function testAPatchOnlySendsTheFieldsPresentInTheMergePatchBody(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MutateInterventionWorkflowCommand $command): bool => ['status' => 'done', 'skipReason' => null] === $command->payload))
      ->willReturn(new MutateInterventionWorkflowResult($this->view()));

    $input = new UpdateInterventionWorkItemInput();
    $input->status = 'done';
    $input->resultResource = '/api/equipment/ignored';

    $this->createProcessor(
      $commandBus,
      'PATCH',
      body: '{"status":"done","skipReason":null}',
      ifMatch: '"revision-1"',
    )->process($input, new Patch(), ['id' => self::WORK_ITEM_ID]);
  }

  #[Test]
  public function testAPatchResolvesAnAssigneeIriIntoAMemberId(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MutateInterventionWorkflowCommand $command): bool => self::MEMBER_ID === $command->payload['assigneeId']))
      ->willReturn(new MutateInterventionWorkflowResult($this->view()));

    $input = new UpdateInterventionWorkItemInput();
    $input->assignee = '/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::MEMBER_ID;

    $this->createProcessor(
      $commandBus,
      'PATCH',
      body: '{"assignee":"/api/organizations/x/members/y"}',
      ifMatch: '"revision-1"',
    )->process($input, new Patch(), ['id' => self::WORK_ITEM_ID]);
  }

  #[Test]
  public function testAPatchUnassignsWhenTheAssigneeIsExplicitlyNull(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MutateInterventionWorkflowCommand $command): bool => null === $command->payload['assigneeId']))
      ->willReturn(new MutateInterventionWorkflowResult($this->view()));

    $this->createProcessor(
      $commandBus,
      'PATCH',
      body: '{"assignee":null}',
      ifMatch: '"revision-1"',
    )->process(new UpdateInterventionWorkItemInput(), new Patch(), ['id' => self::WORK_ITEM_ID]);
  }

  #[Test]
  public function testADeleteReturnsNullWhenTheCommandProducesNoView(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MutateInterventionWorkflowCommand $command): bool => 'delete' === $command->action
        && 3 === $command->expectedRevision))
      ->willReturn(new MutateInterventionWorkflowResult(null));

    $output = $this->createProcessor($commandBus, 'DELETE', ifMatch: '"revision-3"')
      ->process(null, new Delete(), ['id' => self::WORK_ITEM_ID]);

    self::assertNull($output);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $requestStack = $this->requestStack('PATCH', ifMatch: '"revision-1"');

    $processor = new InterventionWorkItemProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      mapper: new InterventionWorkItemOutputFactory($this->createStub(QueryBusPort::class)),
      security: $security,
      requestStack: $requestStack,
      revisionGuard: new RevisionGuard($requestStack),
      creationPreconditionGuard: new CreationPreconditionGuard($requestStack),
      mergePatchFields: new MergePatchFields($requestStack),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new UpdateInterventionWorkItemInput(), new Patch(), ['id' => self::WORK_ITEM_ID]);
  }

  #[Test]
  public function testProcessMapsAMissingInterventionToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      InterventionNotFoundException::withId(self::INTERVENTION_ID),
    );

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus, 'PATCH', ifMatch: '"revision-1"')
      ->process(new UpdateInterventionWorkItemInput(), new Patch(), ['id' => self::WORK_ITEM_ID]);
  }

  private function createProcessor(
    CommandBusPort $commandBus,
    string $method,
    string $body = '{}',
    ?string $ifMatch = null,
    ?string $ifNoneMatch = null,
  ): InterventionWorkItemProcessor {
    $requestStack = $this->requestStack($method, $body, $ifMatch, $ifNoneMatch);

    return new InterventionWorkItemProcessor(
      commandBus: $commandBus,
      mapper: new InterventionWorkItemOutputFactory($this->createStub(QueryBusPort::class)),
      security: $this->securityWithUser(),
      requestStack: $requestStack,
      revisionGuard: new RevisionGuard($requestStack),
      creationPreconditionGuard: new CreationPreconditionGuard($requestStack),
      mergePatchFields: new MergePatchFields($requestStack),
    );
  }

  private function requestStack(
    string $method,
    string $body = '{}',
    ?string $ifMatch = null,
    ?string $ifNoneMatch = null,
  ): RequestStack {
    $server = [];
    if (null !== $ifMatch) {
      $server['HTTP_IF_MATCH'] = $ifMatch;
    }
    if (null !== $ifNoneMatch) {
      $server['HTTP_IF_NONE_MATCH'] = $ifNoneMatch;
    }

    $stack = new RequestStack();
    $stack->push(Request::create(
      '/api/intervention-work-items/' . self::WORK_ITEM_ID,
      $method,
      server: $server,
      content: $body,
    ));

    return $stack;
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }

  private function view(): InterventionWorkflowView
  {
    return new InterventionWorkflowView(
      resource: 'work_item',
      organizationId: self::ORGANIZATION_ID,
      data: [
        'id' => self::WORK_ITEM_ID,
        'intervention' => '/api/interventions/' . self::INTERVENTION_ID,
        'action' => 'inspection',
        'target' => null,
        'resultResource' => null,
        'assignee' => null,
        'source' => 'planned',
        'status' => 'pending',
        'required' => true,
        'skipReason' => null,
        'evidenceCount' => 0,
        'revision' => 1,
        'createdAt' => '2026-01-01T00:00:00+00:00',
        'updatedAt' => '2026-01-01T00:00:00+00:00',
      ],
    );
  }
  // #endregion
}
