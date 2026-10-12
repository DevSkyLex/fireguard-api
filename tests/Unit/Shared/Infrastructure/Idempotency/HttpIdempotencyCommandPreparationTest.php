<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Contract\Idempotency\{IdempotencyBegin, IdempotencyDeniedException, IdempotencyLease, IdempotencyProof, IdempotencyReceipt, IdempotencyScope, IdempotencySnapshot, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Application\Port\Outbound\Crypto\AuthenticatedEncryptionPort;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyPort, IdempotencyReceiptPort, SensitiveIdempotencyProofPort};
use Shared\Application\Service\Idempotency\DurableHttpIdempotencyService;
use Shared\Infrastructure\Idempotency\{HmacIdempotencyFingerprintAdapter, HttpIdempotencyCommandPreparation, HttpIdempotencyEngineCatalog, HttpIdempotencyExecutionContext, HttpIdempotencyPendingCommand, HttpIdempotencyPolicyRegistry, HttpIdempotencyResultCodec};
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function base64_encode;
use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * Class HttpIdempotencyCommandPreparationTest
 *
 * Proves credentials once, verifies signed current lineage and only then permits the durable engine to open a result.
 *
 * @category Adapter Tests
 */
#[CoversClass(HttpIdempotencyCommandPreparation::class)]
final class HttpIdempotencyCommandPreparationTest extends TestCase
{
  #[Test]
  public function legacyUnkeyedCompatibilityDoesNotBindRoleEvidenceWithoutAnExecution(): void
  {
    $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
    $policy->method('supports')->willReturn(true);
    $policy->expects(self::once())->method('authorize')->willReturn(new SensitiveIdempotencyAuthorization('verified-owner', [], []));
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('inspectProof');
    $engine->expects(self::never())->method('begin');
    $evidence = $this->createMock(HttpIdempotencyAuthorizationEvidencePort::class);
    $evidence->expects(self::never())->method('begin');
    [$preparation, $request] = $this->fixture($engine, $policy, null, required: false, evidence: $evidence);
    $preparation->prepare(new PreparationTestCommand());
    self::assertFalse($request->attributes->has('_fireguard_http_idempotency_execution'));
  }

  #[Test]
  public function currentCredentialsAndStoredLineageAreCheckedBeforeBegin(): void
  {
    $steps = [];
    $proof = new IdempotencyProof(['security_revision' => '2'], new DateTimeImmutable('2030-01-01T00:00:00.123456Z'));
    $selected = new IdempotencyProof($proof->metadata, new DateTimeImmutable('2030-01-01T01:00:00.123456+01:00'));
    self::assertNotSame($proof, $selected);
    self::assertNotSame($proof->expiresAt, $selected->expiresAt);
    $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
    $policy->method('supports')->willReturn(true);
    $policy->expects(self::exactly(2))->method('authorize')->willReturnCallback(static function (CommandMessage $command, ?IdempotencyProof $stored) use (&$steps, $proof): SensitiveIdempotencyAuthorization {
      if (null !== $stored) {
        self::assertSame($proof, $stored);
      }
      $steps[] = null === $stored ? 'credentials' : 'lineage';

      return new SensitiveIdempotencyAuthorization('verified-owner', ['password' => 'test-only'], ['security_revision' => '2']);
    });
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('inspectProof')->with(new IdempotencyScope('verified-owner', null, null, 'sensitive-test', '/api/protocol'), 'key')->willReturnCallback(static function () use (&$steps, $proof): IdempotencyProof {
      $steps[] = 'inspect';

      return $proof;
    });
    $engine->expects(self::once())->method('begin')->willReturnCallback(static function (IdempotencyScope $scope, string $key, mixed $input, ?callable $authorize) use (&$steps, $selected): IdempotencyBegin {
      self::assertSame(['http' => ['version' => 1], 'owner' => ['password' => 'test-only']], $input);
      $steps[] = 'begin';
      self::assertNotNull($authorize);
      $authorize($selected);

      return new IdempotencyBegin(replay: new IdempotencySnapshot(200, 'original-result', secret: true, proof: $selected->metadata));
    });
    [$preparation, $request] = $this->fixture($engine, $policy);
    $preparation->prepare(new PreparationTestCommand());
    self::assertSame(['credentials', 'inspect', 'lineage', 'begin'], $steps);
    self::assertFalse($request->attributes->has(HttpIdempotencyCommandPreparation::ATTRIBUTE));
    $state = $request->attributes->get('_fireguard_http_idempotency_execution');
    self::assertInstanceOf(\Shared\Infrastructure\Idempotency\HttpIdempotencyExecutionState::class, $state);
    self::assertSame($selected, $state->storedProof);
    $preparation->prepare(new PreparationTestCommand());
  }

  #[Test]
  public function aCommitBetweenInspectionAndReservationReplaysTheOriginalSensitiveResult(): void
  {
    $steps = [];
    $metadata = ['security_revision' => '2', 'factor_id' => 'original-factor'];
    $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
    $policy->method('supports')->willReturn(true);
    $policy->expects(self::exactly(2))->method('authorize')->willReturnCallback(static function (CommandMessage $command, ?IdempotencyProof $proof) use (&$steps, $metadata): SensitiveIdempotencyAuthorization {
      if (null !== $proof) {
        self::assertSame($metadata, $proof->metadata);
      }
      $steps[] = null === $proof ? 'credentials' : 'selected-lineage';

      return new SensitiveIdempotencyAuthorization('verified-owner', ['proof' => 'same-submission'], $metadata);
    });
    $policy->expects(self::once())->method('replayResult')->willReturnCallback(static function (ResultMessage $result, IdempotencyProof $proof) use ($metadata): ResultMessage {
      self::assertSame($metadata, $proof->metadata);
      self::assertInstanceOf(PreparationTestResult::class, $result);
      self::assertSame('committed-original', $result->value);

      return $result;
    });
    $cipher = $this->createMock(AuthenticatedEncryptionPort::class);
    $cipher->expects(self::once())->method('decrypt')->willReturnCallback(static function () use (&$steps): string {
      $steps[] = 'decrypt';
      $operation = new HttpIdempotencyOperation('sensitive-test', 'auth', 'bus_owner', PreparationTestCommand::class, PreparationTestResult::class, secret: true);

      return json_encode(['v' => 1, 'status' => 200, 'body' => new HttpIdempotencyResultCodec()->encode(new PreparationTestResult('committed-original'), $operation), 'headers' => []], JSON_THROW_ON_ERROR);
    });
    $engine = $this->completionRaceEngine($metadata, $cipher, $steps);
    [$preparation, $request, $context] = $this->fixture($engine, $policy);
    $preparation->prepare(new PreparationTestCommand());
    self::assertTrue($context->isReplay());
    $result = $context->dispatchPrimary(new PreparationTestCommand(), static function (): ResultMessage { self::fail('The already committed factor must not be consumed again.'); });
    self::assertInstanceOf(PreparationTestResult::class, $result);
    self::assertSame('committed-original', $result->value);
    self::assertFalse($request->attributes->has(HttpIdempotencyCommandPreparation::ATTRIBUTE));
    self::assertSame(['credentials', 'inspect-pending', 'reserve-completed', 'selected-lineage', 'decrypt'], $steps);
  }

  #[Test]
  public function revokedSelectedLineageAfterPendingInspectionNeverDecryptsOrActivates(): void
  {
    $steps = [];
    $metadata = ['security_revision' => '2', 'factor_id' => 'revoked-factor'];
    $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
    $policy->method('supports')->willReturn(true);
    $policy->expects(self::exactly(2))->method('authorize')->willReturnCallback(static function (CommandMessage $command, ?IdempotencyProof $proof) use ($metadata): SensitiveIdempotencyAuthorization {
      if (null !== $proof) {
        self::assertSame($metadata, $proof->metadata);

        throw new IdempotencyDeniedException('access_denied');
      }

      return new SensitiveIdempotencyAuthorization('verified-owner', ['proof' => 'same-submission'], $metadata);
    });
    $policy->expects(self::never())->method('replayResult');
    $cipher = $this->createMock(AuthenticatedEncryptionPort::class);
    $cipher->expects(self::never())->method('decrypt');
    [$preparation, $request] = $this->fixture($this->completionRaceEngine($metadata, $cipher, $steps), $policy);

    try {
      $preparation->prepare(new PreparationTestCommand());
      self::fail('Revoked proof cannot reveal the completed factor.');
    } catch (IdempotencyDeniedException $failure) {
      self::assertSame('access_denied', $failure->reason);
      self::assertFalse($failure->operationCompleted);
      self::assertFalse($request->attributes->has('_fireguard_http_idempotency_execution'));
    }
  }

  #[Test]
  public function aFailedFreshProofCannotInspectOrDecodeAReceipt(): void
  {
    $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
    $policy->method('supports')->willReturn(true);
    $policy->expects(self::once())->method('authorize')->willThrowException(new IdempotencyDeniedException('access_denied'));
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('inspectProof');
    $engine->expects(self::never())->method('begin');
    [$preparation] = $this->fixture($engine, $policy);
    $this->expectException(IdempotencyDeniedException::class);
    $preparation->prepare(new PreparationTestCommand());
  }

  #[Test]
  public function onlyAnOwnerVerifiedNativeGrantMayDeriveItsLogicalKey(): void
  {
    $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
    $policy->method('supports')->willReturn(true);
    $policy->expects(self::once())->method('authorize')->willReturn(new SensitiveIdempotencyAuthorization('verified-owner', ['code' => 'native-grant'], ['naturalKeyAllowed' => 'true']));
    $fingerprints = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('x', 32)));
    $expectedKey = $fingerprints->digest('native.grant.key', ['sensitive-test', 'verified-owner', ['code' => 'native-grant']]);
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('inspectProof')->with(self::isInstanceOf(IdempotencyScope::class), $expectedKey)->willReturn(null);
    $engine->expects(self::once())->method('begin')->with(self::isInstanceOf(IdempotencyScope::class), $expectedKey, self::anything())->willReturn(new IdempotencyBegin(lease: new IdempotencyLease('scope', 'key', 'input', 'fence', new DateTimeImmutable('2030-01-01'))));
    [$preparation] = $this->fixture($engine, $policy, null);
    $preparation->prepare(new PreparationTestCommand());
  }

  #[Test]
  public function aRequiredHeaderIsCheckedAfterFreshProofAndBeforeReceiptInspection(): void
  {
    $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
    $policy->method('supports')->willReturn(true);
    $policy->expects(self::once())->method('authorize')->willReturn(new SensitiveIdempotencyAuthorization('verified-owner', [], []));
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('inspectProof');
    $engine->expects(self::never())->method('begin');
    [$preparation] = $this->fixture($engine, $policy, null);
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('key_required');
    $preparation->prepare(new PreparationTestCommand());
  }

  #[Test]
  public function successiveNativeRotationsUseTheirDistinctVerifiedGrantIdentity(): void
  {
    $keys = [];
    foreach (['refresh-parent', 'refresh-daughter'] as $identity) {
      $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
      $policy->method('supports')->willReturn(true);
      $policy->expects(self::once())->method('authorize')->willReturn(new SensitiveIdempotencyAuthorization('verified-owner', ['client' => 'same-client', 'scope' => 'same-scope'], ['naturalKeyAllowed' => 'true', 'naturalKeyIdentity' => $identity]));
      $engine = $this->createMock(HttpIdempotencyPort::class);
      $engine->expects(self::once())->method('inspectProof')->willReturn(null);
      $engine->expects(self::once())->method('begin')->willReturnCallback(static function (IdempotencyScope $scope, string $key) use (&$keys): IdempotencyBegin {
        $keys[] = $key;

        return new IdempotencyBegin(lease: new IdempotencyLease('scope', 'key', 'input', 'fence', new DateTimeImmutable('2030-01-01')));
      });
      [$preparation] = $this->fixture($engine, $policy, null);
      $preparation->prepare(new PreparationTestCommand());
    }
    self::assertCount(2, $keys);
    self::assertNotSame($keys[0], $keys[1]);
  }

  #[Test]
  public function anUnknownAnonymousCapabilityRemainsNotFoundBeforeAnInvalidHeaderError(): void
  {
    $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
    $policy->method('supports')->willReturn(true);
    $policy->expects(self::once())->method('authorize')->willThrowException(new NotFoundHttpException('Capability not found.'));
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('inspectProof');
    $engine->expects(self::never())->method('begin');
    [$preparation] = $this->fixture($engine, $policy, null, invalidKey: true);
    $this->expectException(NotFoundHttpException::class);
    $preparation->prepare(new PreparationTestCommand());
  }

  #[Test]
  public function invalidSensitiveHeaderIsRejectedOnlyAfterCurrentOwnerProof(): void
  {
    $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
    $policy->method('supports')->willReturn(true);
    $policy->expects(self::once())->method('authorize')->willReturn(new SensitiveIdempotencyAuthorization('verified-owner', [], []));
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('inspectProof');
    $engine->expects(self::never())->method('begin');
    [$preparation] = $this->fixture($engine, $policy, null, invalidKey: true);
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('key_invalid');
    $preparation->prepare(new PreparationTestCommand());
  }

  #[Test]
  public function verifiedNativeScopeIgnoresUnrelatedAmbientTenantsAndPreservesItsPrivacySubject(): void
  {
    foreach (['ambient-tenant-a', 'ambient-tenant-b'] as $ambientTenant) {
      $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
      $policy->method('supports')->willReturn(true);
      $policy->expects(self::once())->method('authorize')->willReturn(new SensitiveIdempotencyAuthorization('native-feed-scope', [], ['scopeTenantId' => '', 'tenant_id' => 'native-owner-tenant', 'user_id' => 'native-owner']));
      $engine = $this->createMock(HttpIdempotencyPort::class);
      $expected = new IdempotencyScope('native-feed-scope', null, null, 'sensitive-test', '/api/protocol');
      $engine->expects(self::once())->method('inspectProof')->with($expected, 'key')->willReturn(null);
      $engine->expects(self::once())->method('begin')->with($expected, 'key', self::anything())->willReturn(new IdempotencyBegin(lease: new IdempotencyLease('scope', 'key', 'input', 'fence', new DateTimeImmutable('2030-01-01'))));
      [$preparation, $request] = $this->fixture($engine, $policy);
      $request->attributes->set(HttpIdempotencyCommandPreparation::ATTRIBUTE, new HttpIdempotencyPendingCommand(new HttpIdempotencyOperation('sensitive-test', 'auth', 'bus_owner', PreparationTestCommand::class, PreparationTestResult::class, secret: true), 'key', ['version' => 1], '/api/protocol', $ambientTenant, true, ambientPrincipalId: 'unrelated-actor'));
      $preparation->prepare(new PreparationTestCommand());
      $state = $request->attributes->get('_fireguard_http_idempotency_execution');
      self::assertInstanceOf(\Shared\Infrastructure\Idempotency\HttpIdempotencyExecutionState::class, $state);
      self::assertSame('native-owner', $state->proof['user_id']);
      self::assertSame('native-owner-tenant', $state->proof['tenant_id']);
    }
  }

  #[Test]
  public function explicitRouteTenantSurvivesWhenTheProvedOwnerDiffersFromAnAmbientCaller(): void
  {
    $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
    $policy->method('supports')->willReturn(true);
    $policy->expects(self::once())->method('authorize')->willReturn(new SensitiveIdempotencyAuthorization('signup-owner', [], []));
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $scope = new IdempotencyScope('signup-owner', 'verified-host-tenant', null, 'sensitive-test', '/api/protocol');
    $engine->expects(self::once())->method('inspectProof')->with($scope, 'key')->willReturn(null);
    $engine->expects(self::once())->method('begin')->with($scope, 'key', self::anything())->willReturn(new IdempotencyBegin(lease: new IdempotencyLease('scope', 'key', 'input', 'fence', new DateTimeImmutable('2030-01-01'))));
    [$preparation, $request] = $this->fixture($engine, $policy);
    $request->attributes->set(HttpIdempotencyCommandPreparation::ATTRIBUTE, new HttpIdempotencyPendingCommand(new HttpIdempotencyOperation('sensitive-test', 'auth', 'bus_owner', PreparationTestCommand::class, PreparationTestResult::class), 'key', [], '/api/protocol', 'ambient-tenant', true, ambientPrincipalId: 'ambient-actor', routeTenantId: 'verified-host-tenant'));
    $preparation->prepare(new PreparationTestCommand());
  }

  #[Test]
  public function aChangedVerifiedOwnerTenantCannotOpenAnExistingReceipt(): void
  {
    $stored = new IdempotencyProof(['tenant_id' => 'old-owner-tenant'], new DateTimeImmutable('2030-01-01'));
    $policy = $this->createMock(SensitiveIdempotencyProofPort::class);
    $policy->method('supports')->willReturn(true);
    $policy->expects(self::exactly(2))->method('authorize')->willReturnCallback(static function (CommandMessage $command, ?IdempotencyProof $proof): SensitiveIdempotencyAuthorization {
      if (null !== $proof) {
        throw new IdempotencyDeniedException('authorization_unavailable');
      }

      return new SensitiveIdempotencyAuthorization('native-feed-scope', [], ['scopeTenantId' => '', 'tenant_id' => 'new-owner-tenant', 'user_id' => 'owner']);
    });
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::once())->method('inspectProof')->with(new IdempotencyScope('native-feed-scope', null, null, 'sensitive-test', '/api/protocol'), 'key')->willReturn($stored);
    $engine->expects(self::never())->method('begin');
    [$preparation] = $this->fixture($engine, $policy);
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('authorization_unavailable');
    $preparation->prepare(new PreparationTestCommand());
  }

  /**
   * @return array{HttpIdempotencyCommandPreparation, Request, HttpIdempotencyExecutionContext}
   */
  private function fixture(HttpIdempotencyPort $engine, SensitiveIdempotencyProofPort $policy, ?string $key = 'key', bool $invalidKey = false, bool $required = true, ?HttpIdempotencyAuthorizationEvidencePort $evidence = null): array
  {
    $request = new Request();
    $request->attributes->set(HttpIdempotencyCommandPreparation::ATTRIBUTE, new HttpIdempotencyPendingCommand(new HttpIdempotencyOperation('sensitive-test', 'auth', 'bus_owner', PreparationTestCommand::class, PreparationTestResult::class, secret: true), $key, ['version' => 1], '/api/protocol', null, $required, $invalidKey));
    $stack = new RequestStack();
    $stack->push($request);
    $fingerprints = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('x', 32)));
    $context = new HttpIdempotencyExecutionContext($stack, new HttpIdempotencyResultCodec(), $fingerprints, $this->createStub(Connection::class), $this->createStub(Connection::class));

    return [new HttpIdempotencyCommandPreparation($stack, new HttpIdempotencyPolicyRegistry([], [$policy]), new HttpIdempotencyEngineCatalog($engine, $engine), $context, $fingerprints, $evidence), $request, $context];
  }

  /**
   * @param array<string, string> $metadata original committed security facts
   * @param list<string> $steps deterministic visibility and disclosure order
   */
  private function completionRaceEngine(array $metadata, AuthenticatedEncryptionPort $cipher, array &$steps): DurableHttpIdempotencyService
  {
    $now = new DateTimeImmutable('2026-10-12T12:00:00Z');
    $deadline = $now->modify('+5 minutes');
    $fingerprints = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('x', 32)));
    $receipts = $this->createMock(IdempotencyReceiptPort::class);
    $receipts->expects(self::once())->method('find')->willReturnCallback(static function (IdempotencyLease $lookup) use (&$steps): IdempotencyReceipt {
      $steps[] = 'inspect-pending';

      return new IdempotencyReceipt('pending-input', 'original-owner', 'pending', $lookup->expiresAt);
    });
    $receipts->expects(self::once())->method('reserve')->willReturnCallback(static function (IdempotencyLease $candidate) use (&$steps, $deadline, $metadata, $fingerprints): IdempotencyReceipt {
      $steps[] = 'reserve-completed';
      $mac = $fingerprints->digest('proof', [$candidate->scopeHash, $candidate->keyHash, $candidate->fingerprint, $deadline->format('U.u'), $metadata]);

      return new IdempotencyReceipt($candidate->fingerprint, 'original-owner', 'completed', $candidate->expiresAt, 'encrypted-original', true, $deadline, $metadata, $mac);
    });
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);

    return new DurableHttpIdempotencyService($receipts, $fingerprints, $cipher, $clock);
  }
}

final readonly class PreparationTestCommand implements CommandMessage
{
}

final readonly class PreparationTestResult implements ResultMessage
{
  public function __construct(public string $value = 'original')
  {
  }
}
