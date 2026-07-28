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
  CreateInterventionChangeInput,
  UpdateInterventionChangeInput
};
use Intervention\Presentation\Api\Factory\InterventionChangeOutputFactory;
use Intervention\Presentation\Api\Processor\InterventionChangeProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\{CreationPreconditionGuard, MergePatchFields, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

/**
 * Test InterventionChangeProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionChangeProcessor::class)]
final class InterventionChangeProcessorTest extends TestCase
{
  // #region Constants
  private const string CHANGE_ID = '550e8400-e29b-41d4-a716-446655441500';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655441501';

  private const string WORK_ITEM_ID = '550e8400-e29b-41d4-a716-446655441502';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441401';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessCreatesAChangeFromTheCreateInput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MutateInterventionWorkflowCommand $command): bool => 'change' === $command->resource
        && 'create' === $command->action
        && self::USER_ID === $command->userId
        && null === $command->id
        && false === $command->createOnly
        && null === $command->expectedRevision
        && self::INTERVENTION_ID === $command->payload['interventionId']
        && self::WORK_ITEM_ID === $command->payload['workItemId']
        && 'equipment' === $command->payload['resource']
        && ['status' => 'operational'] === $command->payload['patch']))
      ->willReturn(new MutateInterventionWorkflowResult($this->view()));

    $input = new CreateInterventionChangeInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;
    $input->workItem = '/api/intervention-work-items/' . self::WORK_ITEM_ID;
    $input->resource = 'equipment';
    $input->patch = ['status' => 'operational'];

    $output = $this->createProcessor($commandBus, 'POST')->process($input, new Post());

    self::assertNotNull($output);
    self::assertSame(self::CHANGE_ID, $output->id);
    self::assertSame('pending', $output->status);
    self::assertSame(2, $output->revision);
  }

  #[Test]
  public function testProcessLeavesTheWorkItemNullWhenTheInputOmitsIt(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MutateInterventionWorkflowCommand $command): bool => null === $command->payload['workItemId']))
      ->willReturn(new MutateInterventionWorkflowResult($this->view()));

    $input = new CreateInterventionChangeInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;
    $input->resource = 'equipment';

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
        && true === $command->createOnly
        && self::CHANGE_ID === $command->id))
      ->willReturn(new MutateInterventionWorkflowResult($this->view()));

    $input = new CreateInterventionChangeInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;
    $input->resource = 'equipment';

    $this->createProcessor($commandBus, 'PUT', ifNoneMatch: '*')
      ->process($input, new Put(), ['id' => self::CHANGE_ID]);
  }

  #[Test]
  public function testAPatchOnlySendsTheFieldsPresentInTheMergePatchBody(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MutateInterventionWorkflowCommand $command): bool => 'update' === $command->action
        && ['status' => 'applied'] === $command->payload))
      ->willReturn(new MutateInterventionWorkflowResult($this->view()));

    $input = new UpdateInterventionChangeInput();
    $input->status = 'applied';
    $input->patch = ['ignored' => true];

    $this->createProcessor($commandBus, 'PATCH', body: '{"status":"applied"}', ifMatch: '"revision-1"')
      ->process($input, new Patch(), ['id' => self::CHANGE_ID]);
  }

  #[Test]
  public function testAPatchForwardsTheReviewedPatchWhenTheMergePatchBodyCarriesIt(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MutateInterventionWorkflowCommand $command): bool => 'update' === $command->action
        && ['patch' => ['name' => 'Corrected']] === $command->payload))
      ->willReturn(new MutateInterventionWorkflowResult($this->view()));

    $input = new UpdateInterventionChangeInput();
    $input->patch = ['name' => 'Corrected'];

    $this->createProcessor($commandBus, 'PATCH', body: '{"patch":{"name":"Corrected"}}', ifMatch: '"revision-1"')
      ->process($input, new Patch(), ['id' => self::CHANGE_ID]);
  }

  #[Test]
  public function testAPatchForwardsTheExpectedRevisionFromTheIfMatchHeader(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MutateInterventionWorkflowCommand $command): bool => 5 === $command->expectedRevision))
      ->willReturn(new MutateInterventionWorkflowResult($this->view()));

    $this->createProcessor($commandBus, 'PATCH', body: '{}', ifMatch: '"revision-5"')
      ->process(new UpdateInterventionChangeInput(), new Patch(), ['id' => self::CHANGE_ID]);
  }

  #[Test]
  public function testADeleteReturnsNullWhenTheCommandProducesNoView(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (MutateInterventionWorkflowCommand $command): bool => 'delete' === $command->action))
      ->willReturn(new MutateInterventionWorkflowResult(null));

    $output = $this->createProcessor($commandBus, 'DELETE', ifMatch: '"revision-1"')
      ->process(null, new Delete(), ['id' => self::CHANGE_ID]);

    self::assertNull($output);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $requestStack = $this->requestStack('PATCH', ifMatch: '"revision-1"');

    $processor = new InterventionChangeProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      mapper: new InterventionChangeOutputFactory(),
      security: $security,
      requestStack: $requestStack,
      revisionGuard: new RevisionGuard($requestStack),
      creationPreconditionGuard: new CreationPreconditionGuard($requestStack),
      mergePatchFields: new MergePatchFields($requestStack),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new UpdateInterventionChangeInput(), new Patch(), ['id' => self::CHANGE_ID]);
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
      ->process(new UpdateInterventionChangeInput(), new Patch(), ['id' => self::CHANGE_ID]);
  }

  private function createProcessor(
    CommandBusPort $commandBus,
    string $method,
    string $body = '{}',
    ?string $ifMatch = null,
    ?string $ifNoneMatch = null,
  ): InterventionChangeProcessor {
    $requestStack = $this->requestStack($method, $body, $ifMatch, $ifNoneMatch);

    return new InterventionChangeProcessor(
      commandBus: $commandBus,
      mapper: new InterventionChangeOutputFactory(),
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
      '/api/intervention-changes/' . self::CHANGE_ID,
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
      resource: 'change',
      organizationId: '550e8400-e29b-41d4-a716-446655440001',
      data: [
        'id' => self::CHANGE_ID,
        'intervention' => '/api/interventions/' . self::INTERVENTION_ID,
        'workItem' => null,
        'resource' => 'equipment',
        'patch' => ['status' => 'operational'],
        'status' => 'pending',
        'revision' => 2,
        'createdAt' => '2026-01-01T00:00:00+00:00',
        'updatedAt' => '2026-01-01T00:00:00+00:00',
      ],
    );
  }
  // #endregion
}
