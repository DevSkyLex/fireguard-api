<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Contract\Idempotency\{IdempotencyBegin, IdempotencyDeniedException, IdempotencyLease, IdempotencyProof, IdempotencySnapshot};
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{ExternalIdempotencyReconciliationPort, HttpIdempotencyOperationContributionPort, HttpIdempotencyPort, HttpIdempotencyResultProofContributionPort, SensitiveIdempotencyProofPort};
use Shared\Application\Service\Idempotency\{HttpIdempotencyOperationRegistry, HttpIdempotencyResultProofRegistry};
use Shared\Infrastructure\Idempotency\{HmacIdempotencyFingerprintAdapter, HttpIdempotencyExecutionContext, HttpIdempotencyExecutionRecovery, HttpIdempotencyResultCodec, HttpIdempotencySensitiveExecutionProof};
use Shared\Infrastructure\Messaging\Outbox\RootTransactionManagerAdapter;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

use function base64_encode;
use function str_repeat;

/**
 * Class HttpIdempotencyExecutionContextTest
 *
 * Pins claim-before-effect and receipt-before-commit ordering while preserving media root ownership and rollback uncertainty.
 *
 * @category Adapter Tests
 */
#[CoversClass(HttpIdempotencyExecutionContext::class)]
#[CoversClass(HttpIdempotencyExecutionRecovery::class)]
#[CoversClass(HttpIdempotencySensitiveExecutionProof::class)]
#[CoversClass(RootTransactionManagerAdapter::class)]
final class HttpIdempotencyExecutionContextTest extends TestCase
{
  #[Test]
  public function sensitiveReplayRequiresAnAuthenticatedStoredProofBeforeActivation(): void
  {
    $context = $this->context($this->createStub(Connection::class));
    $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
    $policy->expects(self::never())->method('replayResult');
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Sensitive HTTP replay requires its exact currently proved command and receipt proof.');

    $context->activate(self::operation(), $this->createStub(HttpIdempotencyPort::class), new IdempotencyBegin(replay: new IdempotencySnapshot(201, 'unopened')), [], new HttpIdempotencySensitiveExecutionProof($policy, new ExecutionTestCommand()));
  }

  #[Test]
  public function sensitiveProofCannotActivateADifferentPrimaryCommand(): void
  {
    $context = $this->context($this->createStub(Connection::class));
    $policy = $this->createStub(SensitiveIdempotencyProofPort::class);
    $nested = new class () implements CommandMessage {};
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Sensitive HTTP replay requires its exact currently proved command and receipt proof.');

    $context->activate(self::operation(), $this->createStub(HttpIdempotencyPort::class), new IdempotencyBegin(lease: self::lease()), [], new HttpIdempotencySensitiveExecutionProof($policy, $nested));
  }

  #[Test]
  public function ownerResultProofRunsAfterFlushAndBeforeAtomicReceiptCommit(): void
  {
    $steps = [];
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('commit')->willReturnCallback(static function () use (&$steps): void { $steps[] = 'commit'; });
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::once())->method('flush')->willReturnCallback(static function () use (&$steps): void { $steps[] = 'flush'; });
    $contributor = $this->createMock(HttpIdempotencyResultProofContributionPort::class);
    $contributor->method('supports')->willReturn(true);
    $contributor->expects(self::once())->method('proofForResult')->willReturnCallback(static function (HttpIdempotencyOperation $operation, ResultMessage $result, array $facts) use (&$steps): array {
      $steps[] = 'owner-proof';
      self::assertInstanceOf(ExecutionTestResult::class, $result);
      self::assertSame('actor', $facts['principalId']);

      return ['principalId' => 'actor', 'user_id' => 'verified-created-target'];
    });
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('beforeCommit')->willReturnCallback(static function (IdempotencyLease $lease, IdempotencySnapshot $snapshot) use (&$steps): void {
      $steps[] = 'receipt';
      self::assertSame(['principalId' => 'actor', 'user_id' => 'verified-created-target'], $snapshot->proof);
    });
    $stack = new RequestStack();
    $stack->push(new Request());
    $registry = new HttpIdempotencyResultProofRegistry(new HttpIdempotencyOperationRegistry([new ExecutionTestOperationContribution()]), [$contributor]);
    $context = new HttpIdempotencyExecutionContext($stack, new HttpIdempotencyResultCodec(), new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('x', 32))), $connection, $this->createStub(Connection::class), mainEntityManager: $manager, resultProofs: $registry);
    $context->activate(self::operation(), $engine, new IdempotencyBegin(lease: self::lease()), ['principalId' => 'actor']);
    $context->dispatchPrimary(new ExecutionTestCommand(), static function () use (&$steps): ResultMessage {
      $steps[] = 'mutation';

      return new ExecutionTestResult('created');
    });
    self::assertSame(['mutation', 'flush', 'owner-proof', 'receipt', 'commit'], $steps);
  }

  #[Test]
  public function ownerResultProofCannotOverwriteVerifiedScopeAndRollsBackInstead(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::never())->method('commit');
    $connection->expects(self::once())->method('rollBack');
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('beforeCommit');
    $engine->expects(self::once())->method('confirmedRollback');
    $contributor = $this->createMock(HttpIdempotencyResultProofContributionPort::class);
    $contributor->method('supports')->willReturn(true);
    $contributor->expects(self::once())->method('proofForResult')->willReturn(['organizationId' => 'foreign']);
    $stack = new RequestStack();
    $stack->push(new Request());
    $registry = new HttpIdempotencyResultProofRegistry(new HttpIdempotencyOperationRegistry([new ExecutionTestOperationContribution()]), [$contributor]);
    $context = new HttpIdempotencyExecutionContext($stack, new HttpIdempotencyResultCodec(), new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('x', 32))), $connection, $this->createStub(Connection::class), resultProofs: $registry);
    $context->activate(self::operation(), $engine, new IdempotencyBegin(lease: self::lease()), ['organizationId' => 'original']);
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('authorization_unavailable');
    $context->dispatchPrimary(new ExecutionTestCommand(), static fn (): ResultMessage => new ExecutionTestResult('created'));
  }

  #[Test]
  public function duplicateResultProofOwnersFailAtRegistryConstruction(): void
  {
    $contributor = $this->createStub(HttpIdempotencyResultProofContributionPort::class);
    $contributor->method('supports')->willReturn(true);
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Duplicate HTTP result-proof ownership');
    new HttpIdempotencyResultProofRegistry(new HttpIdempotencyOperationRegistry([new ExecutionTestOperationContribution()]), [$contributor, $contributor]);
  }

  #[Test]
  public function itCommitsTheApplicationReceiptBeforeTheOwningBusTransaction(): void
  {
    $steps = [];
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('beginTransaction')->willReturnCallback(static function () use (&$steps): void { $steps[] = 'begin'; });
    $connection->expects(self::once())->method('commit')->willReturnCallback(static function () use (&$steps): void { $steps[] = 'commit'; });
    $connection->expects(self::never())->method('rollBack');
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('claimWithinTransaction')->willReturnCallback(static function () use (&$steps): void { $steps[] = 'claim'; });
    $engine->expects(self::once())->method('beforeCommit')->willReturnCallback(static function (IdempotencyLease $lease, IdempotencySnapshot $snapshot) use (&$steps): void {
      $steps[] = 'receipt';
      self::assertSame(['organizationId' => 'org-1'], $snapshot->proof);
      $decoded = new HttpIdempotencyResultCodec()->decode($snapshot->body, self::operation());
      self::assertInstanceOf(ExecutionTestResult::class, $decoded);
      self::assertSame('saved', $decoded->value);
    });
    $engine->expects(self::never())->method('confirmedRollback');
    $context = $this->context($connection);
    $context->activate(self::operation(), $engine, new IdempotencyBegin(lease: self::lease()), ['organizationId' => 'org-1']);

    $result = $context->dispatchPrimary(new ExecutionTestCommand(), static function () use (&$steps): ResultMessage {
      $steps[] = 'effect';

      return new ExecutionTestResult('saved');
    });

    self::assertInstanceOf(ExecutionTestResult::class, $result);
    self::assertSame('saved', $result->value);
    self::assertSame(['begin', 'claim', 'effect', 'receipt', 'commit'], $steps);
  }

  #[Test]
  public function itLetsAMediaHandlerOwnItsRootAndExplicitlyCaptureAPostCallbackResult(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('beginTransaction');
    $connection->expects(self::once())->method('commit');
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('claimWithinTransaction');
    $engine->expects(self::once())->method('beforeCommit');
    $context = $this->context($connection);
    $context->activate(self::operation('root_owner'), $engine, new IdempotencyBegin(lease: self::lease()), []);
    $root = new RootTransactionManagerAdapter($connection, idempotency: $context, database: 'main');

    $result = $context->dispatchPrimary(new ExecutionTestCommand(), static function () use ($root, $context): ResultMessage {
      $value = $root->transactional(static function () use ($context): string {
        $context->captureResult(new ExecutionTestResult('media'));

        return 'media';
      });

      return new ExecutionTestResult($value);
    });

    self::assertInstanceOf(ExecutionTestResult::class, $result);
    self::assertSame('media', $result->value);
  }

  #[Test]
  public function itReplaysOnlyTheDeclaredResultWithoutInvokingTheHandler(): void
  {
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('claimWithinTransaction');
    $engine->expects(self::never())->method('beforeCommit');
    $context = $this->context($this->createStub(Connection::class));
    $operation = self::operation();
    $snapshot = new IdempotencySnapshot(201, new HttpIdempotencyResultCodec()->encode(new ExecutionTestResult('original'), $operation));
    $context->activate($operation, $engine, new IdempotencyBegin(replay: $snapshot), []);

    $result = $context->dispatchPrimary(new ExecutionTestCommand(), static function (): ResultMessage {
      self::fail('A completed authorized replay must not execute the mutation.');
    });

    self::assertInstanceOf(ExecutionTestResult::class, $result);
    self::assertSame('original', $result->value);
    self::assertTrue($context->isReplay());
  }

  #[Test]
  public function itDoesNotCaptureANestedSameTypeCommandAsThePrimaryOutcome(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('beginTransaction');
    $connection->expects(self::once())->method('commit');
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('beforeCommit')->willReturnCallback(static function (IdempotencyLease $lease, IdempotencySnapshot $snapshot): void {
      $decoded = new HttpIdempotencyResultCodec()->decode($snapshot->body, self::operation());
      self::assertInstanceOf(ExecutionTestResult::class, $decoded);
      self::assertSame('outer', $decoded->value);
    });
    $context = $this->context($connection);
    $context->activate(self::operation(), $engine, new IdempotencyBegin(lease: self::lease()), []);

    $result = $context->dispatchPrimary(new ExecutionTestCommand(), static function () use ($context): ResultMessage {
      $nested = $context->dispatchPrimary(new ExecutionTestCommand(), static fn (): ResultMessage => new ExecutionTestResult('nested'));
      self::assertInstanceOf(ExecutionTestResult::class, $nested);
      self::assertSame('nested', $nested->value);

      return new ExecutionTestResult('outer');
    });

    self::assertInstanceOf(ExecutionTestResult::class, $result);
    self::assertSame('outer', $result->value);
  }

  #[Test]
  public function itReleasesOnlyAfterAConfirmedRollback(): void
  {
    $failure = new RuntimeException('business failure');
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('rollBack');
    $connection->expects(self::never())->method('commit');
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('confirmedRollback')->with(self::lease());
    $engine->expects(self::never())->method('commitUnknown');
    $context = $this->context($connection);
    $context->activate(self::operation(), $engine, new IdempotencyBegin(lease: self::lease()), []);

    try {
      $context->dispatchPrimary(new ExecutionTestCommand(), static fn () => throw $failure);
      self::fail('The primary business failure must propagate.');
    } catch (RuntimeException $actual) {
      self::assertSame($failure, $actual);
    }
  }

  #[Test]
  public function itBlocksUnknownCommitOutcomesWithoutReleasingOwnership(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('commit')->willThrowException(new RuntimeException('acknowledgement lost'));
    $connection->expects(self::once())->method('close');
    $connection->expects(self::never())->method('rollBack');
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('commitUnknown');
    $engine->expects(self::once())->method('reconcile')->willReturn(null);
    $engine->expects(self::never())->method('confirmedRollback');
    $context = $this->context($connection);
    $context->activate(self::operation(), $engine, new IdempotencyBegin(lease: self::lease()), []);
    $this->expectException(CommitOutcomeUnknownException::class);

    $context->dispatchPrimary(new ExecutionTestCommand(), static fn (): ResultMessage => new ExecutionTestResult('saved'));
  }

  #[Test]
  public function uncertainCommitReconciliationRechecksCurrentProofBeforeOpeningTheResult(): void
  {
    $steps = [];
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('commit')->willThrowException(new RuntimeException('acknowledgement lost'));
    $connection->expects(self::once())->method('close')->willReturnCallback(static function () use (&$steps): void { $steps[] = 'close'; });
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('commitUnknown')->willReturnCallback(static function () use (&$steps): void { $steps[] = 'unknown'; });
    $selected = new IdempotencyProof(['organizationId' => 'org-1'], new DateTimeImmutable('2030-01-01'));
    $engine->expects(self::once())->method('reconcile')->willReturnCallback(static function (IdempotencyLease $lease, ?callable $authorize) use (&$steps, $selected): IdempotencySnapshot {
      self::assertNotNull($authorize);
      $authorize($selected);
      $steps[] = 'open-result';

      return new IdempotencySnapshot(201, new HttpIdempotencyResultCodec()->encode(new ExecutionTestResult('durable'), self::operation()));
    });
    $context = $this->context($connection);
    $context->activate(self::operation(), $engine, new IdempotencyBegin(lease: self::lease()), [], authorizeReconciliation: static function (IdempotencyProof $proof) use (&$steps, $selected): void {
      self::assertSame($selected, $proof);
      $steps[] = 'current-gate';
    });
    $result = $context->dispatchPrimary(new ExecutionTestCommand(), static fn (): ResultMessage => new ExecutionTestResult('durable'));
    self::assertInstanceOf(ExecutionTestResult::class, $result);
    self::assertSame('durable', $result->value);
    self::assertSame(['close', 'unknown', 'current-gate', 'open-result'], $steps);
  }

  #[Test]
  public function uncertainCommitCannotOpenAResultAfterCurrentRightsAreRevoked(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('commit')->willThrowException(new RuntimeException('acknowledgement lost'));
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('commitUnknown');
    $engine->expects(self::once())->method('reconcile')->willReturnCallback(static function (IdempotencyLease $lease, ?callable $authorize): ?IdempotencySnapshot {
      self::assertNotNull($authorize);
      $authorize(new IdempotencyProof(['organizationId' => 'org-1'], new DateTimeImmutable('2030-01-01')));
      self::fail('Denied reconciliation cannot open a result.');
    });
    $engine->expects(self::never())->method('confirmedRollback');
    $context = $this->context($connection);
    $context->activate(self::operation(), $engine, new IdempotencyBegin(lease: self::lease()), [], authorizeReconciliation: static fn (IdempotencyProof $proof) => throw new IdempotencyDeniedException('access_denied'));
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('access_denied');
    $context->dispatchPrimary(new ExecutionTestCommand(), static fn (): ResultMessage => new ExecutionTestResult('durable'));
  }

  #[Test]
  public function itRefusesToCommitARootMediaResultThatWasNotCapturedInsideItsTransaction(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::never())->method('commit');
    $connection->expects(self::once())->method('rollBack');
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('confirmedRollback');
    $context = $this->context($connection);
    $context->activate(self::operation('root_owner'), $engine, new IdempotencyBegin(lease: self::lease()), []);
    $root = new RootTransactionManagerAdapter($connection, idempotency: $context, database: 'main');
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('result_unavailable');

    $context->dispatchPrimary(new ExecutionTestCommand(), static function () use ($root): ResultMessage {
      $root->transactional(static fn () => 'private aggregate');

      return new ExecutionTestResult('too late');
    });
  }

  #[Test]
  public function itKeepsProviderIdentityStableAcrossFencesAndIsolatesRequests(): void
  {
    $stack = new RequestStack();
    $stack->push(new Request());
    $context = $this->context($this->createStub(Connection::class), $stack);
    $engine = $this->createStub(HttpIdempotencyPort::class);
    $context->activate(self::operation(), $engine, new IdempotencyBegin(lease: self::lease()), []);
    $first = $context->providerKey('stripe.checkout');
    self::assertNotSame($first, $context->providerKey('stripe.portal'));
    $stack->push(new Request());
    self::assertNull($context->executionLease());
    self::assertFalse($context->isReplay());
    $stack->pop();
    $context->deactivate();
    $other = new IdempotencyLease('scope', 'key', 'input', 'other-fence', new DateTimeImmutable('2030-01-01'));
    $context->activate(self::operation(), $engine, new IdempotencyBegin(lease: $other), []);
    self::assertSame($first, $context->providerKey('stripe.checkout'));
    $context->deactivate();
  }

  #[Test]
  public function itRequestsReconciliationAfterRollbackAndNeverReleasesAnExternalEffect(): void
  {
    $steps = [];
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('rollBack')->willReturnCallback(static function () use (&$steps): void { $steps[] = 'rollback'; });
    $connection->expects(self::never())->method('commit');
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('confirmedRollback');
    $engine->expects(self::once())->method('commitUnknown')->willReturnCallback(static function () use (&$steps): void { $steps[] = 'unknown'; });
    $owner = $this->createMock(ExternalIdempotencyReconciliationPort::class);
    $owner->method('supports')->with('testOperation')->willReturn(true);
    $owner->expects(self::once())->method('requestReconciliation')->with(self::operation(), self::lease(), ['organizationId' => 'org-1'], ['stripe.checkout'])->willReturnCallback(static function () use (&$steps): void { $steps[] = 'reconcile'; });
    $stack = new RequestStack();
    $stack->push(new Request());
    $context = new HttpIdempotencyExecutionContext($stack, new HttpIdempotencyResultCodec(), new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('x', 32))), $connection, $this->createStub(Connection::class), reconciliation: [$owner]);
    $context->activate(self::operation(), $engine, new IdempotencyBegin(lease: self::lease()), ['organizationId' => 'org-1']);

    try {
      $context->dispatchPrimary(new ExecutionTestCommand(), static function () use ($context): ResultMessage {
        $context->externalEffectStarted('stripe.checkout');
        $context->externalEffectStarted('stripe.checkout');

        throw new RuntimeException('provider acknowledgement lost');
      });
      self::fail('An external effect cannot be blindly repeated after local rollback.');
    } catch (IdempotencyDeniedException $failure) {
      self::assertSame('commit_unknown', $failure->reason);
    }
    self::assertSame(['rollback', 'unknown', 'reconcile'], $steps);
  }

  #[Test]
  public function itBindsOnlyTheDeclaredPublicNestedResultIdentifier(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('beforeCommit')->willReturnCallback(static function (IdempotencyLease $lease, IdempotencySnapshot $snapshot): void {
      self::assertSame(['resourceId' => 'org-created', 'resourceKind' => 'organization', 'organizationId' => 'org-created'], $snapshot->proof);
    });
    $context = $this->context($connection);
    $operation = new HttpIdempotencyOperation('nested', 'main', 'bus_owner', ExecutionTestCommand::class, ExecutionNestedResult::class, contractClasses: [ExecutionNestedView::class], resultIdentifierProperty: 'view.id', resourceKind: 'organization');
    $context->activate($operation, $engine, new IdempotencyBegin(lease: self::lease()), []);
    $result = $context->dispatchPrimary(new ExecutionTestCommand(), static fn (): ResultMessage => new ExecutionNestedResult(new ExecutionNestedView('org-created')));
    self::assertInstanceOf(ExecutionNestedResult::class, $result);
    self::assertSame('org-created', $result->view->id);
  }

  private function context(Connection $connection, ?RequestStack $stack = null): HttpIdempotencyExecutionContext
  {
    if (null === $stack) {
      $stack = new RequestStack();
      $stack->push(new Request());
    }

    return new HttpIdempotencyExecutionContext($stack, new HttpIdempotencyResultCodec(), new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('x', 32))), $connection, $this->createStub(Connection::class));
  }

  private static function operation(string $mode = 'bus_owner'): HttpIdempotencyOperation
  {
    return new HttpIdempotencyOperation('testOperation', 'main', $mode, ExecutionTestCommand::class, ExecutionTestResult::class, statusCode: 201);
  }

  private static function lease(): IdempotencyLease
  {
    return new IdempotencyLease('scope', 'key', 'input', 'fence', new DateTimeImmutable('2030-01-01'));
  }
}

final readonly class ExecutionTestCommand implements CommandMessage
{
}

final readonly class ExecutionTestResult implements ResultMessage
{
  public function __construct(public string $value)
  {
  }
}

final readonly class ExecutionNestedResult implements ResultMessage
{
  public function __construct(public ExecutionNestedView $view)
  {
  }
}

final readonly class ExecutionNestedView
{
  public function __construct(public string $id)
  {
  }
}

final readonly class ExecutionTestOperationContribution implements HttpIdempotencyOperationContributionPort
{
  public static function definitions(): array
  {
    return [new HttpIdempotencyOperation('testOperation', 'main', 'bus_owner', ExecutionTestCommand::class, ExecutionTestResult::class, statusCode: 201)];
  }
}
