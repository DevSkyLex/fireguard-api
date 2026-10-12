<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Adapter\Idempotency;

use Authorization\Application\Port\Inbound\{AuthorizationPort, FreshAuthorizationPort};
use Authorization\Infrastructure\Adapter\Idempotency\EvidenceAuthorizationAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyAccount;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyPlatformAuthorizationEvidencePort};

/**
 * Class EvidenceAuthorizationAdapterTest
 *
 * Restricts fresh predicate substitution to the exact principal verified for a durable attempt.
 *
 * @category Adapter Tests
 */
#[CoversClass(EvidenceAuthorizationAdapter::class)]
final class EvidenceAuthorizationAdapterTest extends TestCase
{
  #[Test]
  public function untrackedCallsPreserveEveryOriginalOwnerPredicateEntryPoint(): void
  {
    $inner = $this->createMock(AuthorizationPort::class);
    $inner->expects(self::once())->method('hasPermission')->with('actor', 'users.read')->willReturn(true);
    $inner->expects(self::once())->method('hasRole')->with('actor', 'admin')->willReturn(true);
    $inner->expects(self::once())->method('hasAnyRole')->with('actor', ['admin', 'operator'])->willReturn(true);
    $inner->expects(self::once())->method('hasAllRoles')->with('actor', ['admin'])->willReturn(true);
    $evidence = $this->createMock(HttpIdempotencyPlatformAuthorizationEvidencePort::class);
    $evidence->expects(self::exactly(4))->method('tracksPrincipal')->with('actor')->willReturn(false);
    $evidence->expects(self::never())->method('recordPlatform');
    $accounts = $this->createMock(HttpIdempotencyAccountPort::class);
    $accounts->expects(self::never())->method('current');
    $fresh = $this->createMock(FreshAuthorizationPort::class);
    $fresh->expects(self::never())->method('hasPermission');
    $fresh->expects(self::never())->method('hasRole');
    $adapter = new EvidenceAuthorizationAdapter($inner, $fresh, $evidence, $accounts);

    self::assertTrue($adapter->hasPermission('actor', 'users.read'));
    self::assertTrue($adapter->hasRole('actor', 'admin'));
    self::assertTrue($adapter->hasAnyRole('actor', ['admin', 'operator']));
    self::assertTrue($adapter->hasAllRoles('actor', ['admin']));
  }

  #[Test]
  public function aTrackedPredicateUsesFreshAssignmentsAndRecordsOnlyItsActualGrant(): void
  {
    $inner = $this->createMock(AuthorizationPort::class);
    $inner->expects(self::never())->method('hasPermission');
    $accounts = $this->createStub(HttpIdempotencyAccountPort::class);
    $accounts->method('current')->willReturn(new HttpIdempotencyAccount('actor', 'tenant', 4, true, true));
    $fresh = $this->createMock(FreshAuthorizationPort::class);
    $fresh->expects(self::exactly(2))->method('hasPermission')->with('actor', 'users.read', 'tenant')->willReturnOnConsecutiveCalls(true, false);
    $evidence = $this->createMock(HttpIdempotencyPlatformAuthorizationEvidencePort::class);
    $evidence->method('tracksPrincipal')->willReturn(true);
    $evidence->expects(self::once())->method('recordPlatform')->with('actor', 'permission', ['users.read']);
    $adapter = new EvidenceAuthorizationAdapter($inner, $fresh, $evidence, $accounts);

    self::assertTrue($adapter->hasPermission('actor', 'users.read'));
    self::assertFalse($adapter->hasPermission('actor', 'users.read'));
  }
}
