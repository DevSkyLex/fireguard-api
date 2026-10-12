<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application\Service\Idempotency;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Idempotency\{IdempotencyCompletion, IdempotencyDeniedException, IdempotencyLease, IdempotencyProof, IdempotencyReceipt, IdempotencyScope, IdempotencySnapshot};
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Application\Port\Outbound\Crypto\AuthenticatedEncryptionPort;
use Shared\Application\Port\Outbound\Idempotency\IdempotencyReceiptPort;
use Shared\Application\Service\Idempotency\DurableHttpIdempotencyService;
use Shared\Infrastructure\Crypto\VersionedAesGcmAdapter;
use Shared\Infrastructure\Idempotency\HmacIdempotencyFingerprintAdapter;

use function array_unique;
use function base64_encode;
use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/** Proves result replay windows, scope binding and denial policies above persistence. */
final class DurableHttpIdempotencyServiceTest extends TestCase
{
  private DateTimeImmutable $now;

  protected function setUp(): void
  {
    $this->now = new DateTimeImmutable('2026-10-11T12:00:00Z');
  }

  public function testReservesFreshFenceWithOnlyDigests(): void
  {
    $receipts = $this->createMock(IdempotencyReceiptPort::class);
    $receipts->expects(self::once())->method('reserve')->willReturnCallback(function (IdempotencyLease $candidate, DateTimeImmutable $now): IdempotencyReceipt {
      self::assertEquals($this->now, $now);
      self::assertEquals($now->modify('+60 seconds'), $candidate->expiresAt);
      self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $candidate->ownerToken);
      self::assertStringNotContainsString('password', $candidate->fingerprint);

      return new IdempotencyReceipt($candidate->fingerprint, $candidate->ownerToken, 'pending', $candidate->expiresAt);
    });
    $begin = $this->engine($receipts)->begin($this->scope(), 'client-key', ['password' => 'test-credential']);
    self::assertInstanceOf(IdempotencyLease::class, $begin->lease);
    self::assertNull($begin->replay);
  }

  public function testReplaysOriginalApplicationResultAndNeverRenewsDeadline(): void
  {
    $receipts = $this->createMock(IdempotencyReceiptPort::class);
    $snapshot = new IdempotencySnapshot(201, '{"v":1,"result":{"id":"original"}}', ['result-type' => 'create']);
    $deadline = $this->now->modify('+24 hours');
    $receipts->expects(self::exactly(2))->method('reserve')->willReturnCallback(fn (IdempotencyLease $candidate): IdempotencyReceipt => $this->completedReceipt($candidate, $snapshot, $deadline));
    $engine = $this->engine($receipts);
    self::assertEquals($snapshot, $engine->begin($this->scope(), 'key', [])->replay);
    $this->now = $deadline;
    $this->assertDenied('expired', static fn () => $engine->begin(new IdempotencyScope('user', 'tenant', 'organization', 'create', '/api/resources'), 'key', []));
  }

  public function testMismatchIsDeniedEvenForExpiredOrInFlightReceipt(): void
  {
    $receipts = $this->createStub(IdempotencyReceiptPort::class);
    $receipts->method('reserve')->willReturn(new IdempotencyReceipt(str_repeat('a', 64), 'owner', 'pending', $this->now->modify('-1 hour')));
    $this->assertDenied('input_mismatch', fn () => $this->engine($receipts)->begin($this->scope(), 'key', ['altered' => true]));
  }

  public function testDeniesInFlightAndUnknownOutcomes(): void
  {
    foreach (['pending' => 'in_progress', 'writing' => 'in_progress', 'outcome_unknown' => 'commit_unknown'] as $state => $reason) {
      $receipts = $this->createStub(IdempotencyReceiptPort::class);
      $receipts->method('reserve')->willReturnCallback(static fn (IdempotencyLease $candidate): IdempotencyReceipt => new IdempotencyReceipt($candidate->fingerprint, 'another-owner', $state, $candidate->expiresAt));
      $this->assertDenied($reason, fn () => $this->engine($receipts)->begin($this->scope(), 'key', []));
    }
  }

  public function testScopesPrincipalTenantOrganizationOperationAndResource(): void
  {
    $hashes = [];
    $receipts = $this->createStub(IdempotencyReceiptPort::class);
    $receipts->method('reserve')->willReturnCallback(static function (IdempotencyLease $candidate) use (&$hashes): IdempotencyReceipt {
      $hashes[] = $candidate->scopeHash;

      return new IdempotencyReceipt($candidate->fingerprint, $candidate->ownerToken, 'pending', $candidate->expiresAt);
    });
    $engine = $this->engine($receipts);
    foreach ([
      $this->scope(),
      new IdempotencyScope('another', 'tenant', 'organization', 'create', '/api/resources'),
      new IdempotencyScope('user', 'another', 'organization', 'create', '/api/resources'),
      new IdempotencyScope('user', 'tenant', 'another', 'create', '/api/resources'),
      new IdempotencyScope('user', 'tenant', 'organization', 'delete', '/api/resources'),
      new IdempotencyScope('user', 'tenant', 'organization', 'create', '/api/resources/another'),
    ] as $scope) {
      $engine->begin($scope, 'same-key', []);
    }
    self::assertCount(6, array_unique($hashes));
  }

  public function testSecretSnapshotIsEncryptedBeforePersistenceAndExpiresFiveMinutesLater(): void
  {
    $receipts = $this->createMock(IdempotencyReceiptPort::class);
    $lease = $this->lease();
    $saved = null;
    $receipts->expects(self::once())->method('complete')->willReturnCallback(function (IdempotencyLease $candidate, IdempotencyCompletion $completion) use (&$saved): void {
      self::assertTrue($completion->secret);
      self::assertEquals($this->now, $completion->completedAt);
      self::assertEquals($completion->completedAt->modify('+5 minutes'), $completion->expiresAt);
      self::assertStringNotContainsString('one-time-recovery', $completion->payload);
      $saved = new IdempotencyReceipt($candidate->fingerprint, $candidate->ownerToken, 'completed', $candidate->expiresAt, $completion->payload, true, $completion->expiresAt, $completion->proof, $completion->proofMac);
    });
    $receipts->method('find')->willReturnCallback(static function () use (&$saved): ?IdempotencyReceipt { return $saved; });
    $engine = $this->engine($receipts);
    $snapshot = new IdempotencySnapshot(200, 'one-time-recovery', secret: true);
    $engine->beforeCommit($lease, $snapshot);
    self::assertEquals($snapshot, $engine->reconcile($lease));
    $this->now = $this->now->modify('+5 minutes');
    $this->assertDenied('expired', static fn () => $engine->reconcile($lease));
  }

  public function testCiphertextCannotMoveToAnotherScopedReceipt(): void
  {
    $receipts = $this->createStub(IdempotencyReceiptPort::class);
    $cipher = new VersionedAesGcmAdapter(['test' => base64_encode(str_repeat('k', 32))], 'test');
    $lease = $this->lease();
    $ciphertext = $cipher->encrypt('{"v":1,"status":200,"body":"secret","headers":[]}', 'another-receipt');
    $receipt = $this->completedReceipt($lease, new IdempotencySnapshot(200, 'secret', secret: true), $this->now->modify('+5 minutes'), $ciphertext);
    $receipts->method('find')->willReturn($receipt);
    $this->assertDenied('result_unavailable', fn () => $this->engine($receipts)->reconcile($lease));
  }

  public function testUnknownOutcomeIsNotTreatedAsConfirmedRollback(): void
  {
    $receipts = $this->createMock(IdempotencyReceiptPort::class);
    $lease = $this->lease();
    $receipts->expects(self::once())->method('markUnknown')->with($lease);
    $receipts->expects(self::never())->method('releaseAfterRollback');
    $receipts->method('find')->willReturn(new IdempotencyReceipt($lease->fingerprint, $lease->ownerToken, 'outcome_unknown', $lease->expiresAt));
    $engine = $this->engine($receipts);
    $engine->commitUnknown($lease);
    self::assertNull($engine->reconcile($lease));
  }

  public function testConfirmedRollbackDelegatesOnlyOwnedFenceRelease(): void
  {
    $receipts = $this->createMock(IdempotencyReceiptPort::class);
    $lease = $this->lease();
    $receipts->expects(self::once())->method('releaseAfterRollback')->with($lease);
    $this->engine($receipts)->confirmedRollback($lease);
  }

  public function testProofInspectionAuthenticatesMetadataWithoutOpeningResultBytes(): void
  {
    $receipts = $this->createStub(IdempotencyReceiptPort::class);
    $snapshot = new IdempotencySnapshot(200, 'unreadable-encrypted-result', secret: true, proof: ['organizationId' => 'original-org', 'securityRevision' => '7']);
    $receipts->method('find')->willReturnCallback(fn (IdempotencyLease $lookup): IdempotencyReceipt => $this->completedReceipt(new IdempotencyLease($lookup->scopeHash, $lookup->keyHash, 'original-input', 'owner', $lookup->expiresAt), $snapshot, $this->now->modify('+5 minutes'), 'malformed ciphertext'));
    $proof = $this->engine($receipts)->inspectProof($this->scope(), 'key');
    self::assertNotNull($proof);
    self::assertSame($snapshot->proof, $proof->metadata);
  }

  public function testProofAndAbsoluteDeadlineTamperingAreDeniedBeforeReplay(): void
  {
    foreach (['proof', 'deadline'] as $tampering) {
      $receipts = $this->createStub(IdempotencyReceiptPort::class);
      $lease = $this->lease();
      $original = $this->completedReceipt($lease, new IdempotencySnapshot(200, 'secret', proof: ['securityRevision' => '1']), $this->now->modify('+5 minutes'));
      $changed = new IdempotencyReceipt($original->fingerprint, $original->ownerToken, $original->state, $original->leaseExpiresAt, $original->payload, $original->secret, 'deadline' === $tampering ? $this->now->modify('+24 hours') : $original->resultExpiresAt, 'proof' === $tampering ? ['securityRevision' => '2'] : $original->proof, $original->proofMac);
      $receipts->method('find')->willReturn($changed);
      $this->assertDenied('result_unavailable', fn () => $this->engine($receipts)->reconcile($lease));
    }
  }

  public function testSelectedProofIsAuthorizedBeforeBeginAndReconciliationDecryptTheOriginalResult(): void
  {
    foreach (['begin', 'reconcile'] as $method) {
      $steps = [];
      $metadata = ['securityRevision' => '7', 'subject' => 'committed-factor'];
      $snapshot = new IdempotencySnapshot(200, 'original-secret', secret: true, proof: $metadata);
      $deadline = $this->now->modify('+5 minutes');
      $receipts = $this->createStub(IdempotencyReceiptPort::class);
      $receipts->method('reserve')->willReturnCallback(fn (IdempotencyLease $lease): IdempotencyReceipt => $this->completedReceipt($lease, $snapshot, $deadline, 'ciphertext'));
      $receipts->method('find')->willReturnCallback(fn (IdempotencyLease $lease): IdempotencyReceipt => $this->completedReceipt($lease, $snapshot, $deadline, 'ciphertext'));
      $cipher = $this->createMock(AuthenticatedEncryptionPort::class);
      $cipher->expects(self::once())->method('decrypt')->willReturnCallback(static function () use (&$steps): string {
        $steps[] = 'decrypt';

        return '{"v":1,"status":200,"body":"original-secret","headers":[]}';
      });
      $gate = static function (IdempotencyProof $proof) use (&$steps, $metadata, $deadline): void {
        self::assertSame($metadata, $proof->metadata);
        self::assertEquals($deadline, $proof->expiresAt);
        $steps[] = 'authorize-selected';
      };
      $engine = $this->engine($receipts, $cipher);
      $actual = 'begin' === $method ? $engine->begin($this->scope(), 'key', [], $gate)->replay : $engine->reconcile($this->lease(), $gate);
      self::assertEquals($snapshot, $actual);
      self::assertSame(['authorize-selected', 'decrypt'], $steps);
    }
  }

  public function testRevokedSelectedProofCannotDecryptOrAcknowledgeEvenAnExpiredReceipt(): void
  {
    foreach (['begin', 'reconcile'] as $method) {
      foreach ([$this->now->modify('+5 minutes'), $this->now->modify('-1 second')] as $deadline) {
        $snapshot = new IdempotencySnapshot(200, 'unreadable', secret: true, proof: ['securityRevision' => '7']);
        $receipts = $this->createStub(IdempotencyReceiptPort::class);
        $receipts->method('reserve')->willReturnCallback(fn (IdempotencyLease $lease): IdempotencyReceipt => $this->completedReceipt($lease, $snapshot, $deadline, 'malformed ciphertext'));
        $receipts->method('find')->willReturnCallback(fn (IdempotencyLease $lease): IdempotencyReceipt => $this->completedReceipt($lease, $snapshot, $deadline, 'malformed ciphertext'));
        $cipher = $this->createMock(AuthenticatedEncryptionPort::class);
        $cipher->expects(self::never())->method('decrypt');
        $engine = $this->engine($receipts, $cipher);
        $gate = static function (IdempotencyProof $proof): void {
          self::assertSame('7', $proof->metadata['securityRevision']);

          throw new IdempotencyDeniedException('access_denied');
        };

        try {
          'begin' === $method ? $engine->begin($this->scope(), 'key', [], $gate) : $engine->reconcile($this->lease(), $gate);
          self::fail('A revoked proof cannot reveal a result or its completion.');
        } catch (IdempotencyDeniedException $failure) {
          self::assertSame('access_denied', $failure->reason);
          self::assertFalse($failure->operationCompleted);
        }
      }
    }
  }

  public function testTamperedSelectedProofCannotReachTheOwnerGateOrDecrypt(): void
  {
    $receipts = $this->createStub(IdempotencyReceiptPort::class);
    $receipts->method('reserve')->willReturnCallback(static fn (IdempotencyLease $lease): IdempotencyReceipt => new IdempotencyReceipt($lease->fingerprint, 'owner', 'completed', $lease->expiresAt, 'ciphertext', true, $lease->expiresAt, ['securityRevision' => 'forged'], 'invalid-mac'));
    $cipher = $this->createMock(AuthenticatedEncryptionPort::class);
    $cipher->expects(self::never())->method('decrypt');
    $engine = $this->engine($receipts, $cipher);
    $gate = static function (IdempotencyProof $proof): void { self::fail('The owner gate cannot consume unauthenticated facts.'); };
    $this->assertDenied('result_unavailable', fn () => $engine->begin($this->scope(), 'key', [], $gate));
  }

  public function testLifecyclePurgesUseDedicatedDigests(): void
  {
    $receipts = $this->createMock(IdempotencyReceiptPort::class);
    $hmac = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('h', 32)));
    $receipts->expects(self::once())->method('purgePrincipal')->with($hmac->digest('principal', 'deleted-user'), 'deleted-user');
    $receipts->expects(self::once())->method('purgeOrganization')->with($hmac->digest('organization', 'deleted-org'));
    $engine = $this->engine($receipts);
    $engine->purgePrincipal('deleted-user');
    $engine->purgeOrganization('deleted-org');
  }

  public function testClaimIndexesFreshOrganizationEvidenceWithItsDedicatedDigest(): void
  {
    $receipts = $this->createMock(IdempotencyReceiptPort::class);
    $lease = $this->lease();
    $hmac = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('h', 32)));
    $receipts->expects(self::once())->method('claim')->with($lease, $this->now, $hmac->digest('organization', 'current-org'));
    $this->engine($receipts)->claimWithinTransaction($lease, 'current-org');
  }

  public function testOrganizationBoundLeaseRejectsDifferentFreshOwnerBeforeClaiming(): void
  {
    $receipts = $this->createMock(IdempotencyReceiptPort::class);
    $receipts->expects(self::never())->method('claim');
    $hmac = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('h', 32)));
    $lease = new IdempotencyLease('scope', 'key', 'fingerprint', 'owner', $this->now->modify('+60 seconds'), organizationHash: $hmac->digest('organization', 'original-org'));
    $this->assertDenied('owner_lost', fn () => $this->engine($receipts)->claimWithinTransaction($lease, 'different-org'));
  }

  public function testReplayWindowRemainsExactlyTwentyFourHoursAcrossDaylightSavingTransition(): void
  {
    $this->now = new DateTimeImmutable('2026-10-25 01:30:00', new DateTimeZone('Europe/Paris'));
    $receipts = $this->createMock(IdempotencyReceiptPort::class);
    $receipts->expects(self::once())->method('complete')->willReturnCallback(static function (IdempotencyLease $lease, IdempotencyCompletion $completion): void {
      self::assertSame(86400, $completion->expiresAt->getTimestamp() - $completion->completedAt->getTimestamp());
    });
    $this->engine($receipts)->beforeCommit($this->lease(), new IdempotencySnapshot(200, 'result'));
  }

  private function completedReceipt(IdempotencyLease $lease, IdempotencySnapshot $snapshot, DateTimeImmutable $expiresAt, ?string $payload = null): IdempotencyReceipt
  {
    $mac = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('h', 32)))->digest('proof', [$lease->scopeHash, $lease->keyHash, $lease->fingerprint, $expiresAt->format('U.u'), $snapshot->proof]);

    return new IdempotencyReceipt($lease->fingerprint, $lease->ownerToken, 'completed', $lease->expiresAt, $payload ?? json_encode(['v' => 1, 'status' => $snapshot->statusCode, 'body' => $snapshot->body, 'headers' => $snapshot->headers], JSON_THROW_ON_ERROR), $snapshot->secret, $expiresAt, $snapshot->proof, $mac);
  }

  private function engine(IdempotencyReceiptPort $receipts, ?AuthenticatedEncryptionPort $cipher = null): DurableHttpIdempotencyService
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturnCallback(fn (): DateTimeImmutable => $this->now);

    return new DurableHttpIdempotencyService($receipts, new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('h', 32))), $cipher ?? new VersionedAesGcmAdapter(['test' => base64_encode(str_repeat('k', 32))], 'test'), $clock);
  }

  private function scope(): IdempotencyScope
  {
    return new IdempotencyScope('user', 'tenant', 'organization', 'create', '/api/resources');
  }

  private function lease(): IdempotencyLease
  {
    return new IdempotencyLease(str_repeat('s', 64), str_repeat('k', 64), str_repeat('f', 64), str_repeat('o', 64), $this->now->modify('+60 seconds'));
  }

  private function assertDenied(string $reason, callable $operation): void
  {
    try {
      $operation();
      self::fail('A denied replay must not execute.');
    } catch (IdempotencyDeniedException $exception) {
      self::assertSame($reason, $exception->reason);
    }
  }
}
