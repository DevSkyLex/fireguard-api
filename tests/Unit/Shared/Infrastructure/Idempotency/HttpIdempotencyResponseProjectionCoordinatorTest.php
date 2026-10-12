<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\{CommandMessage, QueryMessage, ResultMessage, VoidResult};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyResponseProjectionPort;
use Shared\Infrastructure\Idempotency\HttpIdempotencyResponseProjectionCoordinator;
use Shared\Infrastructure\Idempotency\ResponseProjection\HttpIdempotencyResponseProjectionCodec;
use Shared\Infrastructure\Symfony\Adapter\Inbound\MessengerQueryBusAdapter;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use Symfony\Component\Messenger\Stamp\HandledStamp;

use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * Test HttpIdempotencyResponseProjectionCoordinatorTest.
 *
 * Pins exact-query replay, before-commit response capture and bounded owner-declared result reconstruction.
 *
 * @category Unit Tests
 */
#[CoversClass(HttpIdempotencyResponseProjectionCoordinator::class)]
#[CoversClass(HttpIdempotencyResponseProjectionCodec::class)]
#[CoversClass(MessengerQueryBusAdapter::class)]
final class HttpIdempotencyResponseProjectionCoordinatorTest extends TestCase
{
  #[Test]
  public function itPreservesTheInitialRevisionAndOriginalPostwriteResponseAcrossLaterWrites(): void
  {
    $revision = 2;
    $reads = 0;
    $bus = $this->createMock(MessageBusInterface::class);
    $bus->expects(self::exactly(3))->method('dispatch')->willReturnCallback(static function (QueryMessage $query) use (&$revision, &$reads): Envelope {
      ++$reads;

      return new Envelope($query, [new HandledStamp(new ProjectionTestResult($revision, new DateTimeImmutable('2030-01-01T12:00:00+02:00')), 'projection')]);
    });
    $stack = new RequestStack();
    $stack->push(new Request());
    $projections = new HttpIdempotencyResponseProjectionCoordinator($stack, new HttpIdempotencyResponseProjectionCodec(), $bus, [new ProjectionTestContributor()]);
    $queries = new MessengerQueryBusAdapter($bus, $projections);
    $projections->activate(self::operation());

    self::assertSame(2, self::projection($queries->ask(new ProjectionTestQuery('item', 'actor')))->revision);
    $projections->primaryStarted(new ProjectionTestCommand());
    $revision = 3;
    $bundle = $projections->beforeCommit(new VoidResult());
    $projections->primaryFinished();
    $revision = 4;
    self::assertSame(3, self::projection($queries->ask(new ProjectionTestQuery('item', 'actor')))->revision);
    self::assertSame(3, self::projection($queries->ask(new ProjectionTestOutputQuery('item', 'actor')))->revision);
    self::assertSame(3, $reads);

    $projections->deactivate();
    $projections->activate(self::operation());
    $projections->replay($bundle);
    self::assertSame(2, self::projection($queries->ask(new ProjectionTestQuery('item', 'actor')))->revision);
    $projections->primaryStarted(new ProjectionTestCommand());
    $projections->primaryFinished();
    $saved = self::projection($queries->ask(new ProjectionTestOutputQuery('item', 'actor')));
    self::assertSame(3, self::projection($saved)->revision);
    self::assertSame('2030-01-01T12:00:00+02:00', $saved->at->format('c'));
    self::assertSame(3, $reads);
  }

  #[Test]
  public function itRefusesADifferentQueryScopeEvenWhenTheQueryClassIsDeclared(): void
  {
    $bus = $this->createStub(MessageBusInterface::class);
    $bus->method('dispatch')->willReturnCallback(static fn (QueryMessage $query): Envelope => new Envelope($query, [new HandledStamp(new ProjectionTestResult(3, new DateTimeImmutable('2030-01-01')), 'projection')]));
    $stack = new RequestStack();
    $stack->push(new Request());
    $projections = new HttpIdempotencyResponseProjectionCoordinator($stack, new HttpIdempotencyResponseProjectionCodec(), $bus, [new ProjectionTestContributor()]);
    $queries = new MessengerQueryBusAdapter($bus, $projections);
    $projections->activate(self::operation());
    $projections->primaryStarted(new ProjectionTestCommand());
    $bundle = $projections->beforeCommit(new VoidResult());
    $projections->deactivate();
    $projections->activate(self::operation());
    $projections->replay($bundle);
    $projections->primaryStarted(new ProjectionTestCommand());
    $projections->primaryFinished();
    $this->expectException(IdempotencyDeniedException::class);

    $queries->ask(new ProjectionTestOutputQuery('item', 'other-actor'));
  }

  #[Test]
  public function itRequiresTheCurrentOwnerBoundaryBeforeAnyReceiptParsing(): void
  {
    $stack = new RequestStack();
    $stack->push(new Request());
    $projections = new HttpIdempotencyResponseProjectionCoordinator($stack, new HttpIdempotencyResponseProjectionCodec(), $this->createStub(MessageBusInterface::class));
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('current owner authorization');

    $projections->replay('not even a valid cached payload');
  }

  #[Test]
  public function itKeepsRequestsIsolated(): void
  {
    $stack = new RequestStack();
    $stack->push(new Request());
    $projections = new HttpIdempotencyResponseProjectionCoordinator($stack, new HttpIdempotencyResponseProjectionCodec(), $this->createStub(MessageBusInterface::class));
    $projections->activate(self::operation());
    $stack->push(new Request());
    self::assertSame(9, self::projection($projections->dispatch(new ProjectionTestQuery('item', 'actor'), static fn (): ResultMessage => new ProjectionTestResult(9, new DateTimeImmutable('2030-01-01'))))->revision);
    $stack->pop();
    $projections->deactivate();
  }

  #[Test]
  public function itRefusesUndeclaredResultClassesBeforeInvokingTheirConstructor(): void
  {
    ProjectionUndeclaredResult::$constructed = 0;
    $schema = new HttpIdempotencyQueryProjection(ProjectionTestQuery::class, ProjectionTestResult::class);
    $this->expectException(IdempotencyDeniedException::class);

    try {
      new HttpIdempotencyResponseProjectionCodec()->decodeResult(json_encode(['version' => 1, 'value' => ['kind' => 'object', 'class' => ProjectionUndeclaredResult::class, 'fields' => []]], JSON_THROW_ON_ERROR), $schema);
    } finally {
      self::assertSame(0, ProjectionUndeclaredResult::$constructed);
    }
  }

  #[Test]
  public function itRejectsOversizedProjectionValues(): void
  {
    $this->expectException(IdempotencyDeniedException::class);

    new HttpIdempotencyResponseProjectionCodec()->encodeQuery(new ProjectionTestQuery(str_repeat('x', 600000), 'actor'), new HttpIdempotencyQueryProjection(ProjectionTestQuery::class, ProjectionTestResult::class));
  }

  #[Test]
  public function itRejectsRelativeDateExpressionsInAnEncodedResult(): void
  {
    $schema = new HttpIdempotencyQueryProjection(ProjectionTestQuery::class, ProjectionTestResult::class);
    $encoded = json_encode(['version' => 1, 'value' => ['kind' => 'object', 'class' => ProjectionTestResult::class, 'fields' => ['revision' => 3, 'at' => ['kind' => 'date', 'instant' => 'tomorrow', 'timezone' => 'UTC']]]], JSON_THROW_ON_ERROR);
    $this->expectException(IdempotencyDeniedException::class);

    new HttpIdempotencyResponseProjectionCodec()->decodeResult($encoded, $schema);
  }

  #[Test]
  public function itRefusesAnUndeclaredOutputReadInsteadOfReturningLaterMutableState(): void
  {
    $stack = new RequestStack();
    $stack->push(new Request());
    $projections = new HttpIdempotencyResponseProjectionCoordinator($stack, new HttpIdempotencyResponseProjectionCodec(), $this->createStub(MessageBusInterface::class));
    $projections->activate(self::operation());
    $projections->primaryStarted(new ProjectionTestCommand());
    $projections->beforeCommit(new VoidResult());
    $projections->primaryFinished();
    $this->expectException(IdempotencyDeniedException::class);

    $projections->dispatch(new ProjectionTestAuthorizationQuery(), static function (): ResultMessage {
      self::fail('Undeclared response reads must never query later mutable state.');
    });
  }

  #[Test]
  public function itRunsExplicitAuthorizationQueriesFreshDuringReplay(): void
  {
    $stack = new RequestStack();
    $stack->push(new Request());
    $projections = new HttpIdempotencyResponseProjectionCoordinator($stack, new HttpIdempotencyResponseProjectionCodec(), $this->createStub(MessageBusInterface::class));
    $operation = new HttpIdempotencyOperation('testFreshAuthorization', 'main', 'bus_owner', ProjectionTestCommand::class, VoidResult::class, freshAuthorizationQueries: [ProjectionTestAuthorizationQuery::class]);
    $projections->activate($operation);
    $projections->replay('{"version":1,"operation":"testFreshAuthorization","before":[],"after":[]}');
    self::assertSame(2, self::projection($projections->dispatch(new ProjectionTestAuthorizationQuery(), static fn (): ResultMessage => new ProjectionTestResult(2, new DateTimeImmutable('2030-01-01'))))->revision);
    $projections->primaryStarted(new ProjectionTestCommand());
    $projections->primaryFinished();
    self::assertSame(4, self::projection($projections->dispatch(new ProjectionTestAuthorizationQuery(), static fn (): ResultMessage => new ProjectionTestResult(4, new DateTimeImmutable('2030-01-01'))))->revision);
  }

  private static function projection(ResultMessage $result): ProjectionTestResult
  {
    self::assertInstanceOf(ProjectionTestResult::class, $result);

    return $result;
  }

  private static function operation(): HttpIdempotencyOperation
  {
    return new HttpIdempotencyOperation('testProjection', 'main', 'bus_owner', ProjectionTestCommand::class, VoidResult::class, queryProjections: [
      new HttpIdempotencyQueryProjection(ProjectionTestQuery::class, ProjectionTestResult::class),
      new HttpIdempotencyQueryProjection(ProjectionTestOutputQuery::class, ProjectionTestResult::class, captureBeforePrimary: false),
    ]);
  }
}

final readonly class ProjectionTestCommand implements CommandMessage
{
}

final readonly class ProjectionTestAuthorizationQuery implements QueryMessage
{
}

final readonly class ProjectionTestQuery implements QueryMessage
{
  public function __construct(public string $id, public string $actor)
  {
  }
}

final readonly class ProjectionTestOutputQuery implements QueryMessage
{
  public function __construct(public string $id, public string $actor)
  {
  }
}

final readonly class ProjectionTestResult implements ResultMessage
{
  public function __construct(public int $revision, public DateTimeImmutable $at)
  {
  }
}

final class ProjectionUndeclaredResult implements ResultMessage
{
  public static int $constructed = 0;

  public function __construct()
  {
    ++self::$constructed;
  }
}

final readonly class ProjectionTestContributor implements HttpIdempotencyResponseProjectionPort
{
  public function supports(string $operation): bool
  {
    return 'testProjection' === $operation;
  }

  public function queries(string $operation, CommandMessage $command, ResultMessage $result): array
  {
    return [new ProjectionTestOutputQuery('item', 'actor')];
  }
}
