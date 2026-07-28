<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\{Patch, Put};
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Command\Workflow\MutateInterventionWorkflow\{
  MutateInterventionWorkflowCommand,
  MutateInterventionWorkflowResult
};
use Intervention\Domain\Service\InterventionTransitionPolicy;
use Intervention\Presentation\Api\Dto\Input\UpdateInterventionInput;
use Intervention\Presentation\Api\Factory\InterventionOutputFactory;
use Intervention\Presentation\Api\Processor\InterventionProcessor;
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
      new InterventionOutputFactory(new InterventionTransitionPolicy()),
      $security,
      $stack,
      new RevisionGuard($stack),
      new CreationPreconditionGuard($stack),
      new MergePatchFields($stack),
    );
  }
}
