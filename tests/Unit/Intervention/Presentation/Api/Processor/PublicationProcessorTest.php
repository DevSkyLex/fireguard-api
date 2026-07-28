<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Intervention\Application\Contract\Publication\PublicationView;
use Intervention\Application\UseCase\Command\Publication\RequestPublication\{
  RequestPublicationCommand,
  RequestPublicationResult
};
use Intervention\Domain\Exception\{
  InterventionAccessDeniedException,
  InterventionBlockedException,
  InterventionConflictException,
  InterventionNotFoundException
};
use Intervention\Presentation\Api\Dto\Input\CreatePublicationInput;
use Intervention\Presentation\Api\Factory\PublicationOutputFactory;
use Intervention\Presentation\Api\Processor\PublicationProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  ConflictHttpException,
  NotFoundHttpException,
  UnprocessableEntityHttpException
};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Test PublicationProcessorTest.
 *
 * Publishing an intervention is guarded by an optimistic revision and by
 * domain rules that block it outright. Each refusal carries a distinct
 * meaning for the client — stale revision, not yours, not publishable — so
 * they must not collapse into one status. Both the direct throw and the
 * messenger-wrapped form are exercised.
 *
 * @category Processor Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PublicationProcessor::class)]
final class PublicationProcessorTest extends TestCase
{
  // #region Constants
  private const string PUBLICATION_ID = '550e8400-e29b-41d4-a716-446655485001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655485002';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655485003';
  // #endregion

  // #region Methods
  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function domainFailureProvider(): iterable
  {
    yield 'not entitled' => [
      new InterventionAccessDeniedException('Not your intervention.'),
      AccessDeniedHttpException::class,
    ];
    yield 'unknown intervention' => [
      InterventionNotFoundException::withId(self::INTERVENTION_ID),
      NotFoundHttpException::class,
    ];
    yield 'stale revision' => [
      new InterventionConflictException('The intervention revision is stale.'),
      ConflictHttpException::class,
    ];
    yield 'blocked' => [
      new InterventionBlockedException('The intervention has unresolved work items.'),
      UnprocessableEntityHttpException::class,
    ];
  }

  #[Test]
  public function testProcessRequestsThePublicationAndReturnsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RequestPublicationCommand $command): bool => self::USER_ID === $command->userId
        && self::INTERVENTION_ID === $command->interventionId
        && 7 === $command->interventionRevision))
      ->willReturn(new RequestPublicationResult($this->view()));

    $output = $this->createProcessor($commandBus)->process($this->input(), new Post());

    self::assertSame(self::PUBLICATION_ID, $output->id);
    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $output->intervention);
    self::assertSame(7, $output->interventionRevision);
    self::assertSame('queued', $output->status);
    self::assertNull($output->completedAt);
  }

  #[Test]
  public function testProcessAcceptsABareInterventionIdentifier(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RequestPublicationCommand $command): bool => self::INTERVENTION_ID === $command->interventionId))
      ->willReturn(new RequestPublicationResult($this->view()));

    $input = new CreatePublicationInput();
    $input->intervention = self::INTERVENTION_ID;
    $input->interventionRevision = 7;

    $this->createProcessor($commandBus)->process($input, new Post());
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new PublicationProcessor(
      commandBus: $commandBus,
      outputMapper: new PublicationOutputFactory(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process($this->input(), new Post());
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('domainFailureProvider')]
  public function testProcessMapsEachDirectFailureToItsHttpStatus(Throwable $failure, string $expected): void
  {
    $this->expectException($expected);

    $this->processorThrowing($failure)->process($this->input(), new Post());
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('domainFailureProvider')]
  public function testProcessUnwrapsEachMessengerWrappedFailure(Throwable $failure, string $expected): void
  {
    $this->expectException($expected);

    $this->processorThrowing($this->wrapped($failure))->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->processorThrowing($this->wrapped(new RuntimeException('database is down')))
      ->process($this->input(), new Post());
  }

  private function input(): CreatePublicationInput
  {
    $input = new CreatePublicationInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;
    $input->interventionRevision = 7;

    return $input;
  }

  private function wrapped(Throwable $failure): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new RequestPublicationCommand(self::USER_ID, self::INTERVENTION_ID, 7)),
      [$failure],
    ));
  }

  private function processorThrowing(Throwable $failure): PublicationProcessor
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    return $this->createProcessor($commandBus);
  }

  private function createProcessor(CommandBusPort $commandBus): PublicationProcessor
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

    return new PublicationProcessor(
      commandBus: $commandBus,
      outputMapper: new PublicationOutputFactory(),
      security: $security,
    );
  }

  private function view(): PublicationView
  {
    return new PublicationView(
      id: self::PUBLICATION_ID,
      interventionId: self::INTERVENTION_ID,
      interventionRevision: 7,
      status: 'queued',
      error: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      completedAt: null,
    );
  }
  // #endregion
}
