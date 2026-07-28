<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Application\UseCase\Command\Activity\AddInterventionComment\{AddInterventionCommentCommand, AddInterventionCommentResult};
use Intervention\Domain\Exception\InterventionAccessDeniedException;
use Intervention\Presentation\Api\Dto\Input\CreateInterventionCommentInput;
use Intervention\Presentation\Api\Factory\InterventionActivityOutputFactory;
use Intervention\Presentation\Api\Processor\InterventionActivityProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

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

    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
    $sanitizer->method('sanitize')->willReturnArgument(0);

    $processor = new InterventionActivityProcessor($commandBus, new InterventionActivityOutputFactory(), $security, $sanitizer);
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

    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
    $sanitizer->method('sanitize')->willReturnArgument(0);

    $processor = new InterventionActivityProcessor($commandBus, new InterventionActivityOutputFactory(), $security, $sanitizer);

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new CreateInterventionCommentInput(), new Post(), []);
  }

  #[Test]
  public function itRejectsAPayloadThatIsNotACommentInput(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new InterventionActivityProcessor(
      $commandBus,
      new InterventionActivityOutputFactory(),
      $this->createStub(Security::class),
      $this->createStub(HtmlSanitizerInterface::class),
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid comment payload.');

    $processor->process(new stdClass(), new Post(), ['interventionId' => 'intervention-1']);
  }

  #[Test]
  public function itRejectsAnUnauthenticatedUser(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new InterventionActivityProcessor(
      $commandBus,
      new InterventionActivityOutputFactory(),
      $security,
      $this->createStub(HtmlSanitizerInterface::class),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new CreateInterventionCommentInput(), new Post(), ['interventionId' => 'intervention-1']);
  }

  #[Test]
  public function itMapsAWorkflowExceptionFromTheCommandBus(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      new InterventionAccessDeniedException('Missing organization.interventions.comment permission.'),
    );

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-1', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
    $sanitizer->method('sanitize')->willReturnArgument(0);

    $input = new CreateInterventionCommentInput();
    $input->body = 'A comment';

    $processor = new InterventionActivityProcessor($commandBus, new InterventionActivityOutputFactory(), $security, $sanitizer);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($input, new Post(), ['interventionId' => 'intervention-1']);
  }
}
