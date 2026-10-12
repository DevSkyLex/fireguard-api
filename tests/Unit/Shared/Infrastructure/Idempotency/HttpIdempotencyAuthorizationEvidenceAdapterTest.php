<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\{HttpIdempotencyAccount, HttpIdempotencyOperation};
use Shared\Application\Contract\Idempotency\{IdempotencyBegin, IdempotencyDeniedException, IdempotencyLease, IdempotencySnapshot};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyPort};
use Shared\Infrastructure\Idempotency\{HttpIdempotencyAuthorizationEvidenceAdapter, HttpIdempotencyExecutionState};
use Symfony\Component\HttpFoundation\{Request, RequestStack};

use function range;
use function sprintf;

/**
 * Class HttpIdempotencyAuthorizationEvidenceAdapterTest
 *
 * Pins actor-bound request isolation and the minimal successful requirements signed before an original result commits.
 *
 * @category Adapter Tests
 */
#[CoversClass(HttpIdempotencyAuthorizationEvidenceAdapter::class)]
final class HttpIdempotencyAuthorizationEvidenceAdapterTest extends TestCase
{
  #[Test]
  public function nativeRoleTrackingIsLimitedToTheExactBoundPrincipalAndEndsWithTheRequest(): void
  {
    [$adapter, $request, $stack] = $this->fixture();
    self::assertTrue($adapter->tracksPrincipal('actor'));
    self::assertFalse($adapter->tracksPrincipal('foreign-actor'));
    self::assertFalse($adapter->tracksPrincipal(''));
    $stack->push(new Request());
    self::assertFalse($adapter->tracksPrincipal('actor'));
    $adapter->begin('actor');
    self::assertTrue($adapter->tracksPrincipal('actor'));
    self::assertFalse($adapter->tracksPrincipal('foreign-actor'));
    $adapter->clear();
    self::assertFalse($adapter->tracksPrincipal('actor'));
    $stack->pop();
    self::assertTrue($adapter->tracksPrincipal('actor'));
    $request->attributes->set('_fireguard_http_authorization_principal', 'foreign-actor');
    self::assertFalse($adapter->tracksPrincipal('actor'));
    self::assertFalse($adapter->tracksPrincipal('foreign-actor'));
  }

  #[Test]
  public function authorizedReplayStillRequiresFreshRolesWithoutRecordingNewProofEvidence(): void
  {
    [$adapter] = $this->fixture(replay: true);
    self::assertTrue($adapter->tracksPrincipal('actor'));
    self::assertFalse($adapter->tracksPrincipal('foreign-actor'));
    $adapter->recordPlatform('actor', 'roles_all', ['ROLE_ADMIN']);
    self::assertSame('[]', $adapter->snapshot()['platformAuthorizationRequirements']);
  }

  #[Test]
  public function onlyActuallyCheckedPermissionsAndMembershipAreAccumulatedCanonically(): void
  {
    [$adapter] = $this->fixture();
    $adapter->record('actor', 'z-org', ['resource.write', 'resource.read', 'resource.write']);
    $adapter->record('actor', 'a-org', [], true);
    $adapter->record('actor', 'z-org', ['resource.delete'], true);
    $adapter->record('actor', 'z-org', ['resource.read']);
    self::assertSame([
      'authorizationRequirements' => '[{"organizationId":"a-org","permissions":[],"membership":true},{"organizationId":"z-org","permissions":["resource.delete","resource.read","resource.write"],"membership":true}]',
      'platformAuthorizationRequirements' => '[]',
      'principalSecurityRevision' => '8',
    ], $adapter->snapshot());
  }

  #[Test]
  public function corruptRequestEvidenceCannotBecomeAuthorizationProof(): void
  {
    [$adapter, $request] = $this->fixture();
    $request->attributes->set('_fireguard_http_authorization_evidence', 'corrupt');
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('authorization_unavailable');
    $adapter->snapshot();
  }

  #[Test]
  public function nativeRolePredicatesAreRetainedAsRolesAndDuplicateChecksDoNotGrowTheReceipt(): void
  {
    [$adapter] = $this->fixture();
    $adapter->recordPlatform('actor', 'roles_any', ['ROLE_SUPPORT', 'ROLE_ADMIN', 'ROLE_SUPPORT']);
    $adapter->recordPlatform('actor', 'roles_any', ['ROLE_ADMIN', 'ROLE_SUPPORT']);
    $adapter->recordPlatform('actor', 'permission', ['users.read']);
    self::assertSame(
      '[{"kind":"permission","names":["users.read"]},{"kind":"roles_any","names":["ROLE_ADMIN","ROLE_SUPPORT"]}]',
      $adapter->snapshot()['platformAuthorizationRequirements'],
    );
  }

  #[Test]
  public function auxiliaryRecipientAuthorizationCannotBecomeAnApplicantMembershipRequirement(): void
  {
    [$adapter] = $this->fixture();
    $adapter->record('notification-recipient', 'org', ['organization.members.manage'], true);
    $adapter->recordPlatform('notification-recipient', 'roles_all', ['ROLE_ADMIN']);
    self::assertSame('[]', $adapter->snapshot()['authorizationRequirements']);
    self::assertSame('[]', $adapter->snapshot()['platformAuthorizationRequirements']);
  }

  #[Test]
  public function successfulOutsiderCheckWithNoMembershipPredicateRemainsNonMembershipEvidence(): void
  {
    [$adapter] = $this->fixture();
    $adapter->record('actor', 'org', [], false);
    self::assertSame(
      '[{"organizationId":"org","permissions":[],"membership":false}]',
      $adapter->snapshot()['authorizationRequirements'],
    );
  }

  #[Test]
  public function accountScopedRecipientMutationDoesNotInventAnOrganizationRequirement(): void
  {
    [$adapter] = $this->fixture();
    $adapter->recordPlatform('actor', 'platform_keys_all', ['profile.read']);
    self::assertSame('[]', $adapter->snapshot()['authorizationRequirements']);
    self::assertSame('[{"kind":"platform_keys_all","names":["profile.read"]}]', $adapter->snapshot()['platformAuthorizationRequirements']);
  }

  #[Test]
  public function finalRevisionIsFreshWithoutReplacingTheExecutionResultOrganizationScope(): void
  {
    [$adapter, $request] = $this->fixture();
    $state = $request->attributes->get('_fireguard_http_idempotency_execution');
    self::assertInstanceOf(HttpIdempotencyExecutionState::class, $state);
    self::assertSame('7', $state->proof['principalSecurityRevision']);
    $adapter->record('actor', 'created-org', [], true);
    $facts = $adapter->snapshot();
    self::assertSame('8', $facts['principalSecurityRevision']);
    self::assertArrayNotHasKey('organizationId', $facts);
    self::assertSame('[{"organizationId":"created-org","permissions":[],"membership":true}]', $facts['authorizationRequirements']);
    self::assertSame('created-org', $state->proof['organizationId']);
  }

  #[Test]
  public function absentOrEndedExecutionCannotRecordAuthorizationEvidence(): void
  {
    [$adapter, $request] = $this->fixture();
    $adapter->clear();
    $request->attributes->remove('_fireguard_http_idempotency_execution');
    $adapter->record('actor', 'org', ['resource.write'], true);
    $adapter->recordPlatform('actor', 'roles_all', ['ROLE_ADMIN']);
    self::assertSame([], $adapter->snapshot());
    self::assertFalse($request->attributes->has('_fireguard_http_authorization_evidence'));
  }

  #[Test]
  public function preactivationEvidenceBelongsOnlyToThePrincipalVerifiedByTheCaller(): void
  {
    [$adapter, $request] = $this->fixture();
    $state = $request->attributes->get('_fireguard_http_idempotency_execution');
    self::assertInstanceOf(HttpIdempotencyExecutionState::class, $state);
    $request->attributes->remove('_fireguard_http_idempotency_execution');
    $adapter->clear();
    $adapter->record('actor', 'unbound-org', ['resource.read'], true);
    self::assertFalse($request->attributes->has('_fireguard_http_authorization_evidence'));
    $adapter->begin('actor');
    $adapter->record('notification-recipient', 'foreign-org', ['organization.members.manage'], true);
    $adapter->record('actor', 'org', ['resource.write'], true);
    $adapter->recordPlatform('notification-recipient', 'roles_all', ['ROLE_ADMIN']);
    $adapter->recordPlatform('actor', 'permission', ['profile.read']);
    $beforeActivation = $adapter->snapshot();
    self::assertSame([], $beforeActivation);
    $request->attributes->set('_fireguard_http_idempotency_execution', $state);

    $facts = $adapter->snapshot();
    self::assertSame('[{"organizationId":"org","permissions":["resource.write"],"membership":true}]', $facts['authorizationRequirements']);
    self::assertSame('[{"kind":"permission","names":["profile.read"]}]', $facts['platformAuthorizationRequirements']);
  }

  #[Test]
  public function aReplayCannotReplaceTheOriginalSignedAuthorizationEvidence(): void
  {
    [$adapter, $request] = $this->fixture(replay: true);
    $adapter->record('actor', 'org', ['resource.write'], true);
    $adapter->recordPlatform('actor', 'roles_all', ['ROLE_ADMIN']);
    self::assertSame('[]', $adapter->snapshot()['authorizationRequirements']);
    self::assertSame('[]', $adapter->snapshot()['platformAuthorizationRequirements']);
    self::assertFalse($request->attributes->has('_fireguard_http_authorization_evidence'));
  }

  #[Test]
  public function nestedRequestsKeepTheirRequirementsAndActorsIsolated(): void
  {
    [$adapter, $original, $stack] = $this->fixture();
    $adapter->record('actor', 'outer-org', ['resource.write']);
    $nested = new Request();
    $stack->push($nested);
    $adapter->record('actor', 'nested-org', ['resource.read'], true);
    $nestedFacts = $adapter->snapshot();
    self::assertSame([], $nestedFacts);
    self::assertFalse($nested->attributes->has('_fireguard_http_authorization_evidence'));
    self::assertSame($nested, $stack->pop());
    self::assertSame($original, $stack->getCurrentRequest());
    self::assertSame('[{"organizationId":"outer-org","permissions":["resource.write"],"membership":false}]', $adapter->snapshot()['authorizationRequirements']);
  }

  #[Test]
  public function anUnknownPlatformPredicateFailsClosedForTheCurrentActor(): void
  {
    [$adapter] = $this->fixture();

    try {
      $adapter->recordPlatform('actor', 'unknown', ['ROLE_ADMIN']);
      self::fail('Unknown platform predicate must not be written to a signed receipt.');
    } catch (IdempotencyDeniedException $exception) {
      self::assertSame('authorization_unavailable', $exception->reason);
    }
  }

  #[Test]
  public function organizationEvidenceIsBoundedAndDoesNotWriteTheOverflowingRow(): void
  {
    [$adapter] = $this->fixture();
    foreach (range(1, 32) as $index) {
      $adapter->record('actor', sprintf('org-%02d', $index), [], true);
    }
    $before = $adapter->snapshot();

    try {
      $adapter->record('actor', 'overflow', [], true);
      self::fail('Unbounded organization evidence must be rejected before signing.');
    } catch (IdempotencyDeniedException $exception) {
      self::assertSame('authorization_unavailable', $exception->reason);
    }
    self::assertSame($before, $adapter->snapshot());
  }

  #[Test]
  public function permissionEvidenceRejectsAnOverflowWithoutMutatingTheAcceptedRequirements(): void
  {
    [$adapter] = $this->fixture();
    $permissions = [];
    foreach (range(1, 128) as $index) {
      $permissions[] = sprintf('resource.permission_%03d', $index);
    }
    $adapter->record('actor', 'org', $permissions);
    $before = $adapter->snapshot();

    try {
      $adapter->record('actor', 'org', ['resource.overflow']);
      self::fail('Unbounded permission evidence must be rejected before signing.');
    } catch (IdempotencyDeniedException $exception) {
      self::assertSame('authorization_unavailable', $exception->reason);
    }
    self::assertSame($before, $adapter->snapshot());
  }

  /**
   * @return array{HttpIdempotencyAuthorizationEvidenceAdapter, Request, RequestStack}
   */
  private function fixture(bool $replay = false): array
  {
    $stack = new RequestStack();
    $request = new Request();
    $stack->push($request);
    $operation = new HttpIdempotencyOperation('owner_write', 'main', 'bus_owner', EvidenceAuthorizationTestCommand::class, EvidenceAuthorizationTestResult::class);
    $begin = $replay
      ? new IdempotencyBegin(replay: new IdempotencySnapshot(200, 'opaque-recorded-result'))
      : new IdempotencyBegin(lease: new IdempotencyLease('scope', 'key', 'fingerprint', 'owner', new DateTimeImmutable('+1 minute')));
    $request->attributes->set('_fireguard_http_idempotency_execution', new HttpIdempotencyExecutionState($operation, $this->createStub(HttpIdempotencyPort::class), $begin, ['principalId' => 'actor', 'principalSecurityRevision' => '7', 'organizationId' => 'created-org']));
    $accounts = $this->createMock(HttpIdempotencyAccountPort::class);
    $accounts->method('current')->with('actor')->willReturn(new HttpIdempotencyAccount('actor', 'tenant', 8, true, true));

    $adapter = new HttpIdempotencyAuthorizationEvidenceAdapter($stack, $accounts);
    $adapter->begin('actor');

    return [$adapter, $request, $stack];
  }
}

/** @category Test Fixture */
final readonly class EvidenceAuthorizationTestCommand implements CommandMessage
{
}

/** @category Test Fixture */
final readonly class EvidenceAuthorizationTestResult implements ResultMessage
{
}
