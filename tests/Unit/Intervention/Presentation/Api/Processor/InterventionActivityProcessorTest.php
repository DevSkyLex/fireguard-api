<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Application\UseCase\Command\Activity\AddInterventionComment\{AddInterventionCommentCommand, AddInterventionCommentResult};
use Intervention\Presentation\Api\Dto\Input\CreateInterventionCommentInput;
use Intervention\Presentation\Api\Factory\InterventionActivityOutputFactory;
use Intervention\Presentation\Api\Processor\InterventionActivityProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Test InterventionActivityProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionActivityProcessor::class)]
final class InterventionActivityProcessorTest extends TestCase
{
  #[Test]
  public function itDispatchesTheAddCommentCommandWithTheAuthenticatedUserAndInterventionId(): void
  {
    $view = new InterventionWorkflowView('activity', 'organization-1', [
      'id' => 'activity-1',
      'intervention' => '/api/interventions/intervention-1',
      'kind' => 'comment',
      'event' => 'comment',
      'actor' => '/api/organizations/organization-1/members/member-1',
      'body' => 'A comment',
      'payload' => null,
      'createdAt' => '2026-07-09T13:00:00+00:00',
    ]);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (AddInterventionCommentCommand $command): bool => 'user-1' === $command->userId
        && 'intervention-1' === $command->interventionId
        && 'A comment' === $command->body,
      ))
      ->willReturn(new AddInterventionCommentResult($view));

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-1', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $input = new CreateInterventionCommentInput();
    $input->body = 'A comment';

    $processor = new InterventionActivityProcessor($commandBus, new InterventionActivityOutputFactory(), $security);
    $output = $processor->process($input, new Post(), ['interventionId' => 'intervention-1']);

    self::assertSame('activity-1', $output->id);
    self::assertSame('comment', $output->kind);
    self::assertSame('A comment', $output->body);
  }

  #[Test]
  public function itRejectsAMissingInterventionIdUriVariable(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-1', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $processor = new InterventionActivityProcessor($commandBus, new InterventionActivityOutputFactory(), $security);

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new CreateInterventionCommentInput(), new Post(), []);
  }
}
