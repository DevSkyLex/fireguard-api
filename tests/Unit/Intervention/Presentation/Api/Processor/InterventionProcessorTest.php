<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Command\Workflow\MutateInterventionWorkflow\{
  MutateInterventionWorkflowCommand,
  MutateInterventionWorkflowResult
};
use Intervention\Presentation\Api\Dto\Input\UpdateInterventionInput;
use Intervention\Presentation\Api\Factory\InterventionOutputFactory;
use Intervention\Presentation\Api\Processor\InterventionProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\{CreationPreconditionGuard, MergePatchFields, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

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
      new InterventionOutputFactory(),
      $security,
      $stack,
      new RevisionGuard($stack),
      new CreationPreconditionGuard($stack),
      new MergePatchFields($stack),
    );

    self::assertNull($processor->process($input, new Patch(), ['id' => 'intervention-1']));
  }
}
