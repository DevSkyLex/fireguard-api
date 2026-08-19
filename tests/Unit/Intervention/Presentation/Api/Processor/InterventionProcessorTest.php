<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\{Patch, Put};
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Application\Service\{InterventionActionPolicy, InterventionMemberPolicy};
use Intervention\Application\UseCase\Command\Workflow\MutateInterventionWorkflow\{
  MutateInterventionWorkflowCommand,
  MutateInterventionWorkflowResult
};
use Intervention\Domain\Service\{InterventionChangePolicy, InterventionMutabilityPolicy, InterventionTransitionPolicy};
use Intervention\Presentation\Api\Dto\Input\UpdateInterventionInput;
use Intervention\Presentation\Api\Factory\InterventionOutputFactory;
use Intervention\Presentation\Api\Processor\InterventionProcessor;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\{CreationPreconditionGuard, MergePatchFields, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, PreconditionRequiredHttpException};

#[CoversClass(InterventionProcessor::class)]
final class InterventionProcessorTest extends TestCase
{
  #[Test]
  public function itPreservesExplicitNullsInTheMergePatchCommand(): void
  {
    $request = Request::create(
      '/api/interventions/intervention-1',
      'PATCH',
      server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
      content: '{"site":null,"responsible":null,"dueAt":null}',
    );
    $request->headers->set('If-Match', '"revision-7"');
    $stack = new RequestStack();
    $stack->push($request);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (MutateInterventionWorkflowCommand $command): bool => 7 === $command->expectedRevision
        && [
          'dueAt' => null,
          'siteId' => null,
          'responsibleId' => null,
        ] === $command->payload,
      ))
      ->willReturn(new MutateInterventionWorkflowResult(null));
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-1', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $input = new UpdateInterventionInput();
    $processor = new InterventionProcessor(
      $commandBus,
      new InterventionOutputFactory(new InterventionTransitionPolicy()),
      $security,
      $stack,
      new RevisionGuard($stack),
      new CreationPreconditionGuard($stack),
      new MergePatchFields($stack),
    );

    self::assertNull($processor->process($input, new Patch(), ['id' => 'intervention-1']));
  }

  #[Test]
  public function itMapsParticipantAndLabelPatchFieldsToTheirCommandPayload(): void
  {
    $request = Request::create(
      '/api/interventions/intervention-1',
      'PATCH',
      server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
      content: '{"participants":["/api/organizations/organization-1/members/member-1"],"labelIds":null}',
    );
    $request->headers->set('If-Match', '"revision-7"');
    $stack = new RequestStack();
    $stack->push($request);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (MutateInterventionWorkflowCommand $command): bool => [
          'participants' => ['member-1'],
          'labelIds' => [],
        ] === $command->payload,
      ))
      ->willReturn(new MutateInterventionWorkflowResult(null));

    $input = new UpdateInterventionInput();
    $input->participants = ['/api/organizations/organization-1/members/member-1'];
    $input->labelIds = null;

    self::assertNull(
      $this->processor($commandBus, $stack, $this->securityWithUser())->process($input, new Patch(), ['id' => 'intervention-1']),
    );
  }

  #[Test]
  public function itNullsOutTheParticipantListWhenThePatchSendsNull(): void
  {
    $request = Request::create(
      '/api/interventions/intervention-1',
      'PATCH',
      server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
      content: '{"participants":null}',
    );
    $request->headers->set('If-Match', '"revision-7"');
    $stack = new RequestStack();
    $stack->push($request);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (MutateInterventionWorkflowCommand $command): bool => ['participants' => null] === $command->payload,
      ))
      ->willReturn(new MutateInterventionWorkflowResult(null));

    $input = new UpdateInterventionInput();
    $input->participants = null;

    self::assertNull(
      $this->processor($commandBus, $stack, $this->securityWithUser())->process($input, new Patch(), ['id' => 'intervention-1']),
    );
  }

  #[Test]
  public function itRequiresAnIfNoneMatchPreconditionOnAClientUuidPut(): void
  {
    $request = Request::create('/api/interventions/intervention-1', 'PUT');
    $stack = new RequestStack();
    $stack->push($request);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(PreconditionRequiredHttpException::class);

    $this->processor($commandBus, $stack, $this->securityWithUser())
      ->process(new UpdateInterventionInput(), new Put(), ['id' => 'intervention-1']);
  }

  #[Test]
  public function itRejectsAnUnauthenticatedUser(): void
  {
    $stack = new RequestStack();
    $stack->push(Request::create('/api/interventions/intervention-1', 'PATCH'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $this->expectException(AccessDeniedHttpException::class);

    $this->processor($commandBus, $stack, $security)
      ->process(new UpdateInterventionInput(), new Patch(), ['id' => 'intervention-1']);
  }

  #[Test]
  public function itAdvertisesTheCallerActionCapabilitiesOnAMutationResponse(): void
  {
    $request = Request::create(
      '/api/interventions/intervention-1',
      'PATCH',
      server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
      content: '{"name":"Renamed"}',
    );
    $request->headers->set('If-Match', '"revision-7"');
    $stack = new RequestStack();
    $stack->push($request);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(new MutateInterventionWorkflowResult($this->view()));

    $input = new UpdateInterventionInput();
    $input->name = 'Renamed';

    $output = $this->processor($commandBus, $stack, $this->securityWithUser())
      ->process($input, new Patch(), ['id' => 'intervention-1']);

    self::assertNotNull($output);
    self::assertNotNull(
      $output->allowedActions,
      'A mutation response must advertise the caller action-capability block, like the read paths do.',
    );
  }

  /**
   * A minimal workflow view sufficient for the mapper — these tests cover
   * the processor's routing, not the policy's matrix (see
   * InterventionActionPolicyTest for that).
   */
  private function view(): InterventionWorkflowView
  {
    return new InterventionWorkflowView('intervention', '550e8400-e29b-41d4-a716-446655440001', [
      'id' => '550e8400-e29b-41d4-a716-446655441500',
      'organization' => '/api/organizations/550e8400-e29b-41d4-a716-446655440001',
      'number' => 1,
      'type' => 'site_setup',
      'name' => 'Renamed',
      'description' => null,
      'status' => 'draft',
      'site' => null,
      'responsible' => null,
      'participants' => [],
      'priority' => 'normal',
      'plannedStartAt' => null,
      'dueAt' => null,
      'reviewNote' => null,
      'revision' => 8,
      'facilitiesCount' => 0,
      'equipmentCount' => 0,
      'inspectionsCount' => 0,
      'blockersCount' => 0,
      'workItemsCount' => 0,
      'completedWorkItemsCount' => 0,
      'proposedChangesCount' => 0,
      'commentsCount' => 0,
      'hasSignature' => false,
      'labels' => [],
      'createdAt' => '2026-01-01T00:00:00+00:00',
      'updatedAt' => '2026-01-01T00:00:00+00:00',
    ]);
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-1', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return $security;
  }

  private function processor(CommandBusPort $commandBus, RequestStack $stack, Security $security): InterventionProcessor
  {
    return new InterventionProcessor(
      $commandBus,
      new InterventionOutputFactory(new InterventionTransitionPolicy(), $this->actionPolicy()),
      $security,
      $stack,
      new RevisionGuard($stack),
      new CreationPreconditionGuard($stack),
      new MergePatchFields($stack),
    );
  }

  /**
   * The shared {@see InterventionActionPolicy}, wired with inert
   * collaborators sufficient to compute `allowedActions` without asserting
   * on its content — these tests cover the processor's routing, not the
   * policy's matrix (see InterventionActionPolicyTest for that).
   */
  private function actionPolicy(): InterventionActionPolicy
  {
    return new InterventionActionPolicy(
      $this->createStub(OrganizationAuthorizationPort::class),
      new InterventionMemberPolicy($this->createStub(OrganizationMemberRepositoryPort::class)),
      new InterventionTransitionPolicy(),
      new InterventionMutabilityPolicy(),
      new InterventionChangePolicy(),
    );
  }
}
