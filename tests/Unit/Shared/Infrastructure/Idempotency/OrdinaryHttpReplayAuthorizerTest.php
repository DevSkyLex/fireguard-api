<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\{MockObject, Stub};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\{HttpIdempotencyAccount, HttpIdempotencyAuthorizationRequest, HttpIdempotencyOperation};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyPlatformAuthorityPort};
use Shared\Infrastructure\Idempotency\OrdinaryHttpReplayAuthorizer;

use function in_array;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class OrdinaryHttpReplayAuthorizerTest
 *
 * Rechecks current account, tenant, membership and exact recorded permission predicates before returning receipt authority.
 *
 * @category Adapter Tests
 */
#[CoversClass(OrdinaryHttpReplayAuthorizer::class)]
final class OrdinaryHttpReplayAuthorizerTest extends TestCase
{
  private HttpIdempotencyAccountPort&Stub $accounts;

  private HttpIdempotencyPlatformAuthorityPort&MockObject $platform;

  private HttpIdempotencyOrganizationAuthorityPort&MockObject $organizations;

  private OrdinaryHttpReplayAuthorizer $authorizer;

  /**
   * @var list<string>
   */
  private array $platformKeys = ['', 'profile.read'];

  /**
   * @var list<string>
   */
  private array $permissions = ['organization.resources.write', 'users.read'];

  /**
   * @var list<string>
   */
  private array $roles = ['ROLE_SUPPORT'];

  private bool $member = true;

  protected function setUp(): void
  {
    $this->accounts = $this->createStub(HttpIdempotencyAccountPort::class);
    $this->accounts->method('current')->willReturn(new HttpIdempotencyAccount('actor', 'tenant', 7, true, true));
    $this->platform = $this->createMock(HttpIdempotencyPlatformAuthorityPort::class);
    $this->platform->method('permitsKey')->with(self::isInstanceOf(HttpIdempotencyAccount::class), self::isString())->willReturnCallback(fn (HttpIdempotencyAccount $account, string $key): bool => in_array($key, $this->platformKeys, true));
    $this->platform->method('hasPermission')->with(self::isInstanceOf(HttpIdempotencyAccount::class), self::isString())->willReturnCallback(fn (HttpIdempotencyAccount $account, string $permission): bool => in_array($permission, $this->permissions, true));
    $this->platform->method('hasRole')->with(self::isInstanceOf(HttpIdempotencyAccount::class), self::isString())->willReturnCallback(fn (HttpIdempotencyAccount $account, string $role): bool => in_array($role, $this->roles, true));
    $this->organizations = $this->createMock(HttpIdempotencyOrganizationAuthorityPort::class);
    $this->organizations->method('isMemberOf')->with('actor', self::isString())->willReturnCallback(fn (string $actor, string $organization): bool => $this->member);
    $this->organizations->method('hasPermission')->with('actor', self::isString(), self::isString())->willReturnCallback(fn (string $actor, string $organization, string $permission): bool => in_array($permission, $this->permissions, true));
    $this->authorizer = new OrdinaryHttpReplayAuthorizer($this->accounts, $this->platform, $this->organizations);
  }

  #[Test]
  public function globalDeletionScopeRemainsIndependentOfTheMissingResource(): void
  {
    $scope = $this->authorizer->scope(self::request());
    self::assertSame('actor', $scope->principalId);
    self::assertSame('tenant', $scope->tenantId);
    self::assertNull($scope->organizationId);
    self::assertSame('owner_write', $scope->operation);
    self::assertSame('/api/resources/resource', $scope->resourceUri);
    self::assertSame('org', $this->authorizer->scope(self::request(['organizationId' => 'org']))->organizationId);
  }

  #[Test]
  public function removedMembershipDeniesTheSignedOriginalDeleteScope(): void
  {
    $this->member = false;
    $this->organizations->expects(self::once())->method('isMemberOf')->with('actor', 'org');
    $this->organizations->expects(self::never())->method('hasPermission');
    $this->assertDenied(self::request(), self::proof(), null);
  }

  #[Test]
  public function removedPermissionDeniesEvenWhenMembershipRemains(): void
  {
    $this->permissions = [];
    $this->organizations->expects(self::once())->method('hasPermission')->with('actor', 'org', 'organization.resources.write');
    $this->assertDenied(self::request(), self::proof(), 'org');
  }

  #[Test]
  public function changedPlatformPermissionDeniesTheRecordedPredicate(): void
  {
    $this->permissions = [];
    $proof = self::proof([
      'authorizationRequirements' => '[]',
      'platformAuthorizationRequirements' => self::encode([['kind' => 'permission', 'names' => ['users.read']]]),
    ]);
    $this->platform->expects(self::once())->method('hasPermission')->with(self::isInstanceOf(HttpIdempotencyAccount::class), 'users.read');
    $this->assertDenied(self::request(), $proof, 'org');
  }

  #[Test]
  public function nativeRoleAnyEvidenceUsesActualRolesAndDeniesAfterTheirRemoval(): void
  {
    $proof = self::proof([
      'authorizationRequirements' => '[]',
      'platformAuthorizationRequirements' => self::encode([['kind' => 'roles_any', 'names' => ['ROLE_ADMIN', 'ROLE_SUPPORT']]]),
    ]);
    $this->platform->expects(self::exactly(4))->method('hasRole');
    $this->platform->expects(self::never())->method('hasPermission');
    self::assertSame($proof->metadata, $this->authorizer->authorize(self::request(), $proof, 'org'));
    $this->roles = [];
    $this->assertDenied(self::request(), $proof, 'org');
  }

  #[Test]
  public function allRoleEvidenceCannotBeSatisfiedByOnlyOneCurrentRole(): void
  {
    $proof = self::proof([
      'authorizationRequirements' => '[]',
      'platformAuthorizationRequirements' => self::encode([['kind' => 'roles_all', 'names' => ['ROLE_SUPPORT', 'ROLE_ADMIN']]]),
    ]);
    $this->assertDenied(self::request(), $proof, 'org');
  }

  #[Test]
  public function nativePlatformKeyEvidenceIsRecheckedWithoutTreatingItAsARoleOrPermission(): void
  {
    $proof = self::proof([
      'authorizationRequirements' => '[]',
      'platformAuthorizationRequirements' => self::encode([['kind' => 'platform_keys_all', 'names' => ['profile.read']]]),
    ]);
    $this->platform->expects(self::never())->method('hasPermission');
    $this->platform->expects(self::never())->method('hasRole');
    self::assertSame($proof->metadata, $this->authorizer->authorize(self::request(), $proof, 'org'));
    $this->platformKeys = [''];
    $this->assertDenied(self::request(), $proof, 'org');
  }

  #[Test]
  public function originalCoarsePlatformKeyIsRecheckedWhenTheCurrentDeclarationChanges(): void
  {
    $proof = self::proof(['platformSecurityKey' => 'profile.read']);
    self::assertSame($proof->metadata, $this->authorizer->authorize(self::request(), $proof, 'org'));
    $this->platformKeys = [''];
    $this->assertDenied(self::request(), $proof, 'org');
  }

  #[Test]
  public function unknownPlatformKeyAndUnsupportedSecurityExpressionFailClosed(): void
  {
    $this->assertDenied(self::request(expression: "is_granted('unregistered.permission')"), null, null);
    $this->assertDenied(self::request(expression: "is_granted('profile.read') or true"), null, null);
  }

  #[Test]
  public function outsiderJoinRequestDoesNotAcquireAnInventedMembershipRequirement(): void
  {
    $this->member = false;
    $this->organizations->expects(self::never())->method('isMemberOf');
    $this->organizations->expects(self::never())->method('hasPermission');
    $proof = self::proof(['authorizationRequirements' => self::encode([
      ['organizationId' => 'org', 'permissions' => [], 'membership' => false],
    ])]);
    self::assertSame($proof->metadata, $this->authorizer->authorize(self::request(['organizationId' => 'org']), $proof, 'org'));
  }

  #[Test]
  public function recipientNotificationWithoutAnOrganizationRequirementRemainsAccountScoped(): void
  {
    $this->member = false;
    $this->organizations->expects(self::never())->method('isMemberOf');
    $this->organizations->expects(self::never())->method('hasPermission');
    $metadata = self::proof(['authorizationRequirements' => '[]'])->metadata;
    unset($metadata['organizationId']);
    $proof = new IdempotencyProof($metadata, new DateTimeImmutable('+1 day'));
    self::assertSame($metadata, $this->authorizer->authorize(self::request(), $proof, null));
  }

  #[Test]
  public function membershipCapturedForACreatedOrganizationIsRecheckedFromItsSignedResultScope(): void
  {
    $proof = self::proof(['organizationId' => 'created-org', 'authorizationRequirements' => self::encode([
      ['organizationId' => 'created-org', 'permissions' => [], 'membership' => true],
    ])]);
    $this->organizations->expects(self::exactly(2))->method('isMemberOf')->with('actor', 'created-org');
    self::assertSame($proof->metadata, $this->authorizer->authorize(self::request(), $proof, 'created-org'));
    $this->member = false;
    $this->assertDenied(self::request(), $proof, 'created-org');
  }

  #[Test]
  public function aTransferredResourceAndADifferentRouteOrganizationCannotReuseOriginalAuthority(): void
  {
    $this->organizations->expects(self::never())->method('isMemberOf');
    $this->assertDenied(self::request(), self::proof(), 'foreign-org');
    $this->assertDenied(self::request(['organizationId' => 'foreign-org']), self::proof(), null);
  }

  #[Test]
  public function initialOwnerRouteMismatchUsesTheNotFoundDenialBeforeOrganizationChecks(): void
  {
    $this->organizations->expects(self::never())->method('isMemberOf');
    $this->organizations->expects(self::never())->method('hasPermission');
    $this->assertDenied(self::request(['organizationId' => 'org']), null, 'foreign-org', 'scope_not_found');
  }

  #[Test]
  public function coreCompositionFailsClosedForOrganizationReceiptsButAcceptsAccountReceipts(): void
  {
    $this->authorizer = new OrdinaryHttpReplayAuthorizer($this->accounts, $this->platform);
    $this->assertDenied(self::request(), self::proof(), null);
    $metadata = self::proof(['authorizationRequirements' => '[]'])->metadata;
    unset($metadata['organizationId']);
    self::assertSame($metadata, $this->authorizer->authorize(self::request(), new IdempotencyProof($metadata, new DateTimeImmutable('+1 day')), null));
  }

  #[Test]
  #[DataProvider('identityMismatches')]
  public function signedOriginalActorTenantRevisionAndRouteFactsMustMatch(string $field, string $value): void
  {
    $this->organizations->expects(self::never())->method('isMemberOf');
    $this->assertDenied(self::request(), self::proof([$field => $value]), 'org');
  }

  /**
   * @return iterable<string, array{string, string}>
   */
  public static function identityMismatches(): iterable
  {
    yield 'actor' => ['principalId', 'foreign-actor'];
    yield 'tenant' => ['tenantId', 'foreign-tenant'];
    yield 'revision' => ['principalSecurityRevision', '6'];
    yield 'operation' => ['operation', 'foreign_operation'];
    yield 'route' => ['resourceUri', '/api/resources/other'];
    yield 'version' => ['authorizationVersion', '0'];
  }

  #[Test]
  public function anExpiredReceiptStillRechecksAllCurrentGrantsBeforeTheEngineDecidesReplayExpiry(): void
  {
    $this->organizations->expects(self::once())->method('isMemberOf')->with('actor', 'org');
    $this->organizations->expects(self::once())->method('hasPermission')->with('actor', 'org', 'organization.resources.write');
    $proof = new IdempotencyProof(self::proof()->metadata, new DateTimeImmutable('-1 second'));
    self::assertSame($proof->metadata, $this->authorizer->authorize(self::request(), $proof, 'org'));
  }

  #[Test]
  public function anExpiredReceiptCannotAcknowledgeCompletionAfterPermissionRemoval(): void
  {
    $this->permissions = [];
    $this->organizations->expects(self::once())->method('hasPermission')->with('actor', 'org', 'organization.resources.write');
    $proof = new IdempotencyProof(self::proof()->metadata, new DateTimeImmutable('-1 second'));
    $this->assertDenied(self::request(), $proof, 'org');
  }

  #[Test]
  public function aDeactivatedAccountIsDeniedBeforePlatformOrOrganizationChecks(): void
  {
    $accounts = $this->createMock(HttpIdempotencyAccountPort::class);
    $accounts->expects(self::once())->method('current')->with('actor')->willReturn(new HttpIdempotencyAccount('actor', 'tenant', 7, false, true));
    $this->platform->expects(self::never())->method('permitsKey');
    $this->authorizer = new OrdinaryHttpReplayAuthorizer($accounts, $this->platform, $this->organizations);
    $this->assertDenied(self::request(), self::proof(), 'org');
  }

  #[Test]
  #[DataProvider('invalidRequirements')]
  public function malformedSignedRequirementsFailClosed(string $field, string $value): void
  {
    $this->assertDenied(self::request(), self::proof([$field => $value]), 'org');
  }

  /**
   * @return iterable<string, array{string, string}>
   */
  public static function invalidRequirements(): iterable
  {
    yield 'invalid-json' => ['authorizationRequirements', '{invalid'];
    yield 'non-list' => ['authorizationRequirements', '{"organizationId":"org"}'];
    yield 'non-row' => ['authorizationRequirements', '[null]'];
    yield 'membership-not-bool' => ['authorizationRequirements', '[{"organizationId":"org","permissions":[],"membership":"true"}]'];
    yield 'non-string-permission' => ['authorizationRequirements', '[{"organizationId":"org","permissions":[7],"membership":false}]'];
    yield 'unknown-platform-predicate' => ['platformAuthorizationRequirements', '[{"kind":"unknown","names":["ROLE_SUPPORT"]}]'];
    yield 'non-string-role' => ['platformAuthorizationRequirements', '[{"kind":"roles_any","names":[7]}]'];
  }

  /**
   * @param array<string, mixed> $variables
   */
  private static function request(array $variables = ['id' => 'resource'], ?string $expression = null): HttpIdempotencyAuthorizationRequest
  {
    return new HttpIdempotencyAuthorizationRequest('actor', 'tenant', new HttpIdempotencyOperation('owner_write', 'main', 'bus_owner', OrdinaryAuthorizationTestCommand::class, OrdinaryAuthorizationTestResult::class), '/api/resources/resource', $variables, [], $expression);
  }

  /**
   * @param array<string, string> $extra
   */
  private static function proof(array $extra = []): IdempotencyProof
  {
    return new IdempotencyProof([
      'authorizationVersion' => '1',
      'principalId' => 'actor',
      'tenantId' => 'tenant',
      'operation' => 'owner_write',
      'resourceUri' => '/api/resources/resource',
      'principalSecurityRevision' => '7',
      'platformSecurityKey' => '',
      'organizationId' => 'org',
      'authorizationRequirements' => self::encode([['organizationId' => 'org', 'permissions' => ['organization.resources.write'], 'membership' => true]]),
      'platformAuthorizationRequirements' => '[]',
      ...$extra,
    ], new DateTimeImmutable('+1 day'));
  }

  /**
   * @param list<array<string, mixed>> $rows
   */
  private static function encode(array $rows): string
  {
    return json_encode($rows, JSON_THROW_ON_ERROR);
  }

  private function assertDenied(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof, ?string $organization, string $reason = 'access_denied'): void
  {
    try {
      $this->authorizer->authorize($request, $proof, $organization);
      self::fail('The receipt must not return authority after a current requirement is denied.');
    } catch (IdempotencyDeniedException $exception) {
      self::assertSame($reason, $exception->reason);
    }
  }
}

/** @category Test Fixture */
final readonly class OrdinaryAuthorizationTestCommand implements CommandMessage
{
}

/** @category Test Fixture */
final readonly class OrdinaryAuthorizationTestResult implements ResultMessage
{
}
