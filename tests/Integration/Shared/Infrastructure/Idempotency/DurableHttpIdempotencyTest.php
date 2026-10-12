<?php

declare(strict_types=1);

namespace Tests\Integration\Shared\Infrastructure\Idempotency;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyLease, IdempotencyProof, IdempotencyReceipt, IdempotencyScope, IdempotencySnapshot};
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Application\Service\Idempotency\DurableHttpIdempotencyService;
use Shared\Infrastructure\Crypto\VersionedAesGcmAdapter;
use Shared\Infrastructure\Idempotency\HmacIdempotencyFingerprintAdapter;
use Shared\Infrastructure\Persistence\Doctrine\Repository\DoctrineIdempotencyReceiptRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;

use function base64_encode;
use function str_repeat;

/**
 * Class DurableHttpIdempotencyTest
 *
 * Proves real commit, fencing, rollback and offline replay on both owning PostgreSQL databases.
 *
 * @category Integration Test
 */
#[SkipDatabaseRollback]
final class DurableHttpIdempotencyTest extends KernelTestCase
{
  use RootCommitDatabaseIsolationTrait;

  /**
   * @var list<Connection> independent connections to close
   */
  private array $connections = [];

  private DateTimeImmutable $now;

  protected function setUp(): void
  {
    $this->now = new DateTimeImmutable('2026-10-11T12:00:00Z');
  }

  protected function tearDown(): void
  {
    foreach ($this->connections as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
      $connection->close();
    }
    $this->connections = [];
    parent::tearDown();
  }

  #[DataProvider('databases')]
  public function testSelectedCompletionAfterPendingInspectionIsAuthorizedBeforeReplay(string $database): void
  {
    [$write, $reservation, $observer] = $this->connections($database);
    [$original] = $this->engine($write, $reservation);
    [$retry] = $this->engine($observer, $this->connection($database));
    $lease = $original->begin($this->scope(), 'completion-race', ['value' => 'same'])->lease;
    self::assertInstanceOf(IdempotencyLease::class, $lease);
    self::assertNull($retry->inspectProof($this->scope(), 'completion-race'));
    $metadata = ['principalId' => 'verified-user', 'securityRevision' => '7', 'factorId' => 'original-factor'];
    $snapshot = new IdempotencySnapshot(200, 'one-time-original-result', secret: true, proof: $metadata);
    $write->beginTransaction();
    $original->claimWithinTransaction($lease);
    $original->beforeCommit($lease, $snapshot);
    $write->commit();
    $authorized = false;
    $begin = $retry->begin($this->scope(), 'completion-race', ['value' => 'same'], static function (IdempotencyProof $selected) use (&$authorized, $metadata): void {
      self::assertSame($metadata, $selected->metadata);
      $authorized = true;
    });
    self::assertTrue($authorized);
    self::assertNull($begin->lease);
    self::assertEquals($snapshot, $begin->replay);
    $this->assertDenied('access_denied', fn () => $retry->begin($this->scope(), 'completion-race', ['value' => 'same'], static function (IdempotencyProof $selected) use ($metadata): void {
      self::assertSame($metadata, $selected->metadata);

      throw new IdempotencyDeniedException('access_denied');
    }));
  }

  #[DataProvider('databases')]
  public function testBusinessEffectAndSnapshotCommitTogetherAndRollbackIsRetryable(string $database): void
  {
    [$write, $reservation, $observer] = $this->connections($database);
    $write->executeStatement('CREATE TABLE http_idempotency_test_effects (id VARCHAR(64) PRIMARY KEY)');
    [$engine, $repository] = $this->engine($write, $reservation);
    $lease = $engine->begin($this->scope(), 'client-key', ['value' => 1])->lease;
    self::assertInstanceOf(IdempotencyLease::class, $lease);
    self::assertSame('pending', $this->receipt($repository, $lease)->state);
    $write->beginTransaction();
    $engine->claimWithinTransaction($lease);
    self::assertSame('writing', $this->receipt($repository, $lease)->state);
    $write->executeStatement('INSERT INTO http_idempotency_test_effects (id) VALUES (?)', ['effect']);
    $snapshot = new IdempotencySnapshot(201, '{"v":1,"id":"effect"}');
    $engine->beforeCommit($lease, $snapshot);
    self::assertSame('writing', $this->receipt($repository, $lease)->state);
    self::assertSame(0, $observer->fetchOne('SELECT COUNT(*) FROM http_idempotency_test_effects'));
    $write->rollBack();
    $engine->confirmedRollback($lease);
    self::assertSame('pending', $this->receipt($repository, $lease)->state);
    self::assertSame('', $this->receipt($repository, $lease)->ownerToken);
    $this->assertDenied('input_mismatch', fn () => $engine->begin($this->scope(), 'client-key', ['value' => 2]));

    $retry = $engine->begin($this->scope(), 'client-key', ['value' => 1])->lease;
    self::assertInstanceOf(IdempotencyLease::class, $retry);
    $write->beginTransaction();
    $engine->claimWithinTransaction($retry);
    $write->executeStatement('INSERT INTO http_idempotency_test_effects (id) VALUES (?)', ['effect']);
    $engine->beforeCommit($retry, $snapshot);
    $write->commit();
    self::assertSame(1, $observer->fetchOne('SELECT COUNT(*) FROM http_idempotency_test_effects'));
    self::assertEquals($snapshot, $engine->begin($this->scope(), 'client-key', ['value' => 1])->replay);
    $this->assertDenied('input_mismatch', fn () => $engine->begin($this->scope(), 'client-key', ['value' => 2]));
  }

  #[DataProvider('databases')]
  public function testExpiredPendingLeaseCanBeTakenOverAndOldOwnerIsFencedBeforeWrites(string $database): void
  {
    [$write, $reservation, $competitor] = $this->connections($database);
    [$engine] = $this->engine($write, $reservation);
    $old = $engine->begin($this->scope(), 'key', [])->lease;
    self::assertInstanceOf(IdempotencyLease::class, $old);
    $this->now = $this->now->modify('+61 seconds');
    [$second] = $this->engine($competitor, $this->connection($database));
    $current = $second->begin($this->scope(), 'key', [])->lease;
    self::assertInstanceOf(IdempotencyLease::class, $current);
    self::assertNotSame($old->ownerToken, $current->ownerToken);
    $write->beginTransaction();
    $this->assertDenied('owner_lost', static fn () => $engine->claimWithinTransaction($old));
    $write->rollBack();
    $engine->confirmedRollback($old);
    $competitor->beginTransaction();
    $second->claimWithinTransaction($current);
    $second->beforeCommit($current, new IdempotencySnapshot(200, 'current-result'));
    $competitor->commit();
    $replay = $engine->begin($this->scope(), 'key', [])->replay;
    self::assertInstanceOf(IdempotencySnapshot::class, $replay);
    self::assertSame('current-result', $replay->body);
  }

  #[DataProvider('databases')]
  public function testLongWriteTransactionAndUnknownOutcomeNeverPermitLeaseTakeover(string $database): void
  {
    [$write, $reservation, $competitor] = $this->connections($database);
    [$engine, $repository] = $this->engine($write, $reservation);
    [$second] = $this->engine($competitor, $this->connection($database));
    $lease = $engine->begin($this->scope(), 'key', [])->lease;
    self::assertInstanceOf(IdempotencyLease::class, $lease);
    $write->beginTransaction();
    $engine->claimWithinTransaction($lease);
    $this->now = $this->now->modify('+2 hours');
    $this->assertDenied('in_progress', fn () => $second->begin($this->scope(), 'key', []));
    $engine->commitUnknown($lease);
    self::assertSame('writing', $this->receipt($repository, $lease)->state);
    $write->rollBack();
    $engine->commitUnknown($lease);
    self::assertSame('outcome_unknown', $this->receipt($repository, $lease)->state);
    $this->assertDenied('commit_unknown', fn () => $second->begin($this->scope(), 'key', []));
    self::assertNull($engine->reconcile($lease));
  }

  #[DataProvider('databases')]
  public function testLostCommitAcknowledgementReconcilesDurableApplicationResult(string $database): void
  {
    [$write, $reservation] = $this->connections($database);
    [$engine, $repository] = $this->engine($write, $reservation);
    $lease = $engine->begin($this->scope(), 'key', [])->lease;
    self::assertInstanceOf(IdempotencyLease::class, $lease);
    $write->beginTransaction();
    $engine->claimWithinTransaction($lease);
    $snapshot = new IdempotencySnapshot(200, 'durable-result');
    $engine->beforeCommit($lease, $snapshot);
    $write->commit();
    $engine->commitUnknown($lease);
    self::assertSame('completed', $this->receipt($repository, $lease)->state);
    self::assertEquals($snapshot, $engine->reconcile($lease));
  }

  #[DataProvider('databases')]
  public function testExpiredResultIsErasedButRemainsNonReusableForOfflineRetries(string $database): void
  {
    [$write, $reservation] = $this->connections($database);
    [$engine, $repository] = $this->engine($write, $reservation);
    $lease = $engine->begin($this->scope(), 'offline-key', [])->lease;
    self::assertInstanceOf(IdempotencyLease::class, $lease);
    $write->beginTransaction();
    $engine->claimWithinTransaction($lease);
    $engine->beforeCommit($lease, new IdempotencySnapshot(200, 'ordinary-result'));
    $write->commit();
    $this->now = $this->now->modify('+25 hours');
    self::assertSame(1, $repository->eraseExpiredResults($this->now));
    self::assertNull($this->receipt($repository, $lease)->payload);
    $this->assertDenied('expired', fn () => $engine->begin($this->scope(), 'offline-key', []));
    $engine->purgeScope($this->scope());
    self::assertNull($repository->find($lease));
  }

  #[DataProvider('databases')]
  public function testSecretReplayIsEncryptedBoundAndLimitedToFiveMinutesFromFirstCompletion(string $database): void
  {
    [$write, $reservation] = $this->connections($database);
    [$engine, $repository] = $this->engine($write, $reservation);
    $lease = $engine->begin($this->scope(), 'secret-key', ['credential' => 'unpersisted-secret'])->lease;
    self::assertInstanceOf(IdempotencyLease::class, $lease);
    $write->beginTransaction();
    $engine->claimWithinTransaction($lease);
    $engine->beforeCommit($lease, new IdempotencySnapshot(200, 'recovery-secret', secret: true));
    $write->commit();
    $payload = $this->receipt($repository, $lease)->payload;
    self::assertIsString($payload);
    self::assertStringNotContainsString('recovery-secret', $payload);
    $replay = $engine->begin($this->scope(), 'secret-key', ['credential' => 'unpersisted-secret'])->replay;
    self::assertInstanceOf(IdempotencySnapshot::class, $replay);
    self::assertSame('recovery-secret', $replay->body);
    $this->now = $this->now->modify('+5 minutes');
    $this->assertDenied('expired', fn () => $engine->begin($this->scope(), 'secret-key', ['credential' => 'unpersisted-secret']));
    self::assertSame(1, $repository->eraseExpiredResults($this->now));
    self::assertNull($this->receipt($repository, $lease)->payload);
    $this->now = $this->now->modify('+25 hours');
    $this->assertDenied('expired', fn () => $engine->begin($this->scope(), 'secret-key', ['credential' => 'unpersisted-secret']));
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function databases(): iterable
  {
    yield 'main' => ['main'];
    yield 'auth' => ['auth'];
  }

  #[DataProvider('databases')]
  public function testProofInspectionNeverOpensSecretAndRejectsTamperedMetadataAndDeadline(string $database): void
  {
    [$write, $reservation] = $this->connections($database);
    [$engine] = $this->engine($write, $reservation);
    $scope = new IdempotencyScope('verified-user', 'verified-tenant', null, 'refresh', '/api/auth/refresh');
    $lease = $engine->begin($scope, 'key', [])->lease;
    self::assertInstanceOf(IdempotencyLease::class, $lease);
    $write->beginTransaction();
    $engine->claimWithinTransaction($lease);
    $engine->beforeCommit($lease, new IdempotencySnapshot(200, 'refresh-cookie-secret', secret: true, proof: ['organizationId' => 'original-org', 'sessionId' => 'session', 'securityRevision' => '9']));
    $write->commit();
    $write->executeStatement('UPDATE http_idempotency_receipts SET result_payload = ? WHERE scope_hash = ? AND key_hash = ?', ['malformed ciphertext', $lease->scopeHash, $lease->keyHash]);
    $proof = $engine->inspectProof($scope, 'key');
    self::assertNotNull($proof);
    self::assertSame('session', $proof->metadata['sessionId']);
    $this->assertDenied('result_unavailable', static fn () => $engine->begin($scope, 'key', []));
    $write->executeStatement("UPDATE http_idempotency_receipts SET result_expires_at = result_expires_at + INTERVAL '1 hour' WHERE scope_hash = ? AND key_hash = ?", [$lease->scopeHash, $lease->keyHash]);
    $this->assertDenied('result_unavailable', static fn () => $engine->inspectProof($scope, 'key'));
    $write->executeStatement("UPDATE http_idempotency_receipts SET result_expires_at = result_expires_at - INTERVAL '1 hour', proof_payload = ? WHERE scope_hash = ? AND key_hash = ?", ['{"organizationId":"another-org","sessionId":"session","securityRevision":"9"}', $lease->scopeHash, $lease->keyHash]);
    $this->assertDenied('result_unavailable', static fn () => $engine->inspectProof($scope, 'key'));
  }

  #[DataProvider('databases')]
  public function testLifecyclePurgeRemovesAllOperationUrisIncludingGloballyScopedOriginalOrganization(string $database): void
  {
    [$write, $reservation, $observer] = $this->connections($database);
    [$engine] = $this->engine($write, $reservation);
    foreach ([
      ['user-a', null, '/api/resources/1', 'org-a'],
      ['user-a', 'org-a', '/api/organizations/org-a/resources', 'org-a'],
      ['user-b', null, '/api/resources/2', 'org-a'],
      ['user-b', 'org-b', '/api/organizations/org-b/resources', 'org-b'],
    ] as [$principal, $organization, $uri, $originalOrganization]) {
      $scope = new IdempotencyScope($principal, 'tenant', $organization, 'mutate', $uri);
      $lease = $engine->begin($scope, 'same-key', [])->lease;
      self::assertInstanceOf(IdempotencyLease::class, $lease);
      $write->beginTransaction();
      $engine->claimWithinTransaction($lease);
      $engine->beforeCommit($lease, new IdempotencySnapshot(200, 'result', proof: ['organizationId' => $originalOrganization]));
      $write->commit();
    }
    self::assertSame(4, $observer->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
    $engine->purgeOrganization('org-a');
    self::assertSame(1, $observer->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
    $incompleteScope = new IdempotencyScope('user-a', 'tenant', null, 'mutate', '/api/resources/failed');
    $incomplete = $engine->begin($incompleteScope, 'same-key', [])->lease;
    self::assertInstanceOf(IdempotencyLease::class, $incomplete);
    $write->beginTransaction();
    $engine->claimWithinTransaction($incomplete, 'org-a');
    $write->rollBack();
    $engine->confirmedRollback($incomplete);
    self::assertSame(2, $observer->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
    $engine->purgeOrganization('org-a');
    self::assertSame(1, $observer->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
    $engine->purgePrincipal('user-b');
    self::assertSame(0, $observer->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
  }

  #[DataProvider('databases')]
  public function testPermanentAccountPurgeIncludesVerifiedClientAndFederationAssociationsAfterPayloadErasure(string $database): void
  {
    [$write, $reservation, $observer] = $this->connections($database);
    [$engine, $repository] = $this->engine($write, $reservation);
    foreach ([['user-a', null], ['client:application', 'user-a'], ['federation:flow', 'user-a'], ['client:other', 'user-b'], ['client:application', null]] as $index => [$principal, $associated]) {
      $scope = new IdempotencyScope($principal, 'tenant', null, 'grant', '/api/grants/' . $index);
      $lease = $engine->begin($scope, 'key', [])->lease;
      self::assertInstanceOf(IdempotencyLease::class, $lease);
      $write->beginTransaction();
      $engine->claimWithinTransaction($lease);
      $engine->beforeCommit($lease, new IdempotencySnapshot(200, 'secret-result', secret: true, proof: null === $associated ? [] : ['user_id' => $associated]));
      $write->commit();
    }
    $repository->eraseExpiredResults($this->now->modify('+6 minutes'));
    self::assertSame(5, $observer->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts WHERE result_payload IS NULL'));
    $engine->purgePrincipal('user-a');
    self::assertSame(2, $observer->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
    $engine->purgePrincipal('client:application');
    self::assertSame(1, $observer->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
    $engine->purgePrincipal('user-b');
    self::assertSame(0, $observer->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
  }

  #[DataProvider('databases')]
  public function testErasedExpiredResultStillExposesAuthenticatedProofAndChecksInputBeforeConfirmingCompletion(string $database): void
  {
    [$write, $reservation] = $this->connections($database);
    [$engine, $repository] = $this->engine($write, $reservation);
    $scope = $this->scope();
    $lease = $engine->begin($scope, 'expired-key', ['input' => 'original'])->lease;
    self::assertInstanceOf(IdempotencyLease::class, $lease);
    $write->beginTransaction();
    $engine->claimWithinTransaction($lease);
    $engine->beforeCommit($lease, new IdempotencySnapshot(200, 'original-result', proof: ['organizationId' => 'verified-organization', 'permission' => 'read']));
    $write->commit();
    $this->now = $this->now->modify('+25 hours');
    self::assertSame(1, $repository->eraseExpiredResults($this->now));
    $proof = $engine->inspectProof($scope, 'expired-key');
    self::assertNotNull($proof);
    self::assertSame('read', $proof->metadata['permission']);
    self::assertLessThan($this->now, $proof->expiresAt);

    try {
      $engine->begin($scope, 'expired-key', ['input' => 'altered']);
      self::fail('Changed input cannot confirm an original operation.');
    } catch (IdempotencyDeniedException $failure) {
      self::assertSame('input_mismatch', $failure->reason);
      self::assertFalse($failure->operationCompleted);
    }

    try {
      $engine->begin($scope, 'expired-key', ['input' => 'original']);
      self::fail('Expired result never repeats the original effect.');
    } catch (IdempotencyDeniedException $failure) {
      self::assertSame('expired', $failure->reason);
      self::assertTrue($failure->operationCompleted);
    }
    $write->executeStatement("UPDATE http_idempotency_receipts SET result_expires_at = result_expires_at - INTERVAL '1 hour' WHERE scope_hash = ? AND key_hash = ?", [$lease->scopeHash, $lease->keyHash]);
    $this->assertDenied('result_unavailable', static fn () => $engine->inspectProof($scope, 'expired-key'));

    try {
      $engine->begin($scope, 'expired-key', ['input' => 'original']);
      self::fail('Tampered expiry cannot confirm a completed operation.');
    } catch (IdempotencyDeniedException $failure) {
      self::assertSame('result_unavailable', $failure->reason);
      self::assertFalse($failure->operationCompleted);
    }
  }

  /**
   * @return array{Connection, Connection, Connection}
   */
  private function connections(string $database): array
  {
    $write = $this->connection($database);
    $reservation = $this->connection($database);
    $observer = $this->connection($database);
    self::assertNotSame($write->fetchOne('SELECT pg_backend_pid()'), $reservation->fetchOne('SELECT pg_backend_pid()'));
    self::assertNotSame($write->fetchOne('SELECT pg_backend_pid()'), $observer->fetchOne('SELECT pg_backend_pid()'));

    return [$write, $reservation, $observer];
  }

  private function connection(string $database): Connection
  {
    $url = $_ENV['main' === $database ? 'MAIN_DATABASE_URL' : 'AUTH_DATABASE_URL'];
    self::assertIsString($url);
    $connection = DriverManager::getConnection(['url' => $url]);
    $this->connections[] = $connection;

    return $connection;
  }

  /**
   * @return array{DurableHttpIdempotencyService, DoctrineIdempotencyReceiptRepository}
   */
  private function engine(Connection $write, Connection $reservation): array
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturnCallback(fn (): DateTimeImmutable => $this->now);
    $repository = new DoctrineIdempotencyReceiptRepository($write, $reservation);

    return [new DurableHttpIdempotencyService($repository, new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('h', 32))), new VersionedAesGcmAdapter(['test' => base64_encode(str_repeat('k', 32))], 'test'), $clock), $repository];
  }

  private function scope(): IdempotencyScope
  {
    return new IdempotencyScope('verified-user', 'verified-tenant', 'verified-organization', 'create', '/api/test-effects');
  }

  private function receipt(DoctrineIdempotencyReceiptRepository $repository, IdempotencyLease $lease): IdempotencyReceipt
  {
    $receipt = $repository->find($lease);
    self::assertInstanceOf(IdempotencyReceipt::class, $receipt);

    return $receipt;
  }

  private function assertDenied(string $reason, callable $operation): void
  {
    try {
      $operation();
      self::fail('Denied attempt must not repeat an effect.');
    } catch (IdempotencyDeniedException $exception) {
      self::assertSame($reason, $exception->reason);
    }
  }
}
