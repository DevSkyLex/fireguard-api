<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\Idempotency;

use Auth\Infrastructure\Adapter\Idempotency\EvidenceHttpRoleAuthorizationCheckerAdapter;
use Auth\Infrastructure\Security\User\SecurityUser;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyAccount;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyPlatformAuthorityPort, HttpIdempotencyPlatformAuthorizationEvidencePort};
use stdClass;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\{AccessDecision, AuthorizationCheckerInterface, UserAuthorizationCheckerInterface};

/**
 * Class EvidenceHttpRoleAuthorizationCheckerAdapterTest
 *
 * Native Symfony entry points must recheck current grants while preserving the full checker contract.
 *
 * @category Adapter Tests
 */
#[CoversClass(EvidenceHttpRoleAuthorizationCheckerAdapter::class)]
final class EvidenceHttpRoleAuthorizationCheckerAdapterTest extends TestCase
{
  #[Test]
  public function staleTokenRolesCannotGrantARevokedAdministratorRole(): void
  {
    $user = new SecurityUser('actor', 'actor@example.com', 'hash', ['ROLE_ADMIN']);
    $token = $this->createStub(TokenInterface::class);
    $token->method('getUser')->willReturn($user);
    $tokens = $this->createStub(TokenStorageInterface::class);
    $tokens->method('getToken')->willReturn($token);
    $accounts = $this->createStub(HttpIdempotencyAccountPort::class);
    $accounts->method('current')->willReturn(new HttpIdempotencyAccount('actor', null, 1, true, true));
    $authority = $this->createMock(HttpIdempotencyPlatformAuthorityPort::class);
    $authority->expects(self::once())->method('permitsKey')->with(self::isInstanceOf(HttpIdempotencyAccount::class), 'ROLE_ADMIN')->willReturn(false);
    $evidence = $this->createMock(HttpIdempotencyPlatformAuthorizationEvidencePort::class);
    $evidence->method('tracksPrincipal')->willReturn(true);
    $evidence->expects(self::never())->method('recordPlatform');
    $inner = $this->mockInner();
    $inner->expects(self::never())->method('isGranted');
    $checker = new EvidenceHttpRoleAuthorizationCheckerAdapter($inner, $tokens, $accounts, $authority, $evidence);
    $decision = new AccessDecision();

    self::assertFalse($checker->isGranted('ROLE_ADMIN', accessDecision: $decision));
    self::assertFalse($decision->isGranted);
  }

  #[Test]
  public function explicitUserChecksCaptureTheCurrentActualGrantedRole(): void
  {
    $user = new SecurityUser('actor', 'actor@example.com', 'hash', ['ROLE_USER']);
    $accounts = $this->createStub(HttpIdempotencyAccountPort::class);
    $account = new HttpIdempotencyAccount('actor', null, 4, true, false);
    $accounts->method('current')->willReturn($account);
    $authority = $this->createMock(HttpIdempotencyPlatformAuthorityPort::class);
    $authority->expects(self::once())->method('permitsKey')->with($account, 'ROLE_USER')->willReturn(true);
    $evidence = $this->createMock(HttpIdempotencyPlatformAuthorizationEvidencePort::class);
    $evidence->method('tracksPrincipal')->willReturn(true);
    $evidence->expects(self::once())->method('recordPlatform')->with('actor', 'platform_keys_all', ['ROLE_USER']);
    $checker = new EvidenceHttpRoleAuthorizationCheckerAdapter($this->stubInner(), $this->createStub(TokenStorageInterface::class), $accounts, $authority, $evidence);
    $decision = new AccessDecision();

    self::assertInstanceOf(UserAuthorizationCheckerInterface::class, $checker);
    self::assertTrue($checker->isGrantedForUser($user, 'ROLE_USER', accessDecision: $decision));
    self::assertTrue($decision->isGranted);
  }

  #[Test]
  public function aDisabledOrMismatchedAccountCannotObtainANativeRole(): void
  {
    $accounts = $this->createStub(HttpIdempotencyAccountPort::class);
    $accounts->method('current')->willReturnOnConsecutiveCalls(new HttpIdempotencyAccount('actor', null, 1, false, true), new HttpIdempotencyAccount('other', null, 1, true, true));
    $authority = $this->createMock(HttpIdempotencyPlatformAuthorityPort::class);
    $authority->expects(self::never())->method('permitsKey');
    $evidence = $this->createMock(HttpIdempotencyPlatformAuthorizationEvidencePort::class);
    $evidence->method('tracksPrincipal')->willReturn(true);
    $evidence->expects(self::never())->method('recordPlatform');
    $checker = new EvidenceHttpRoleAuthorizationCheckerAdapter($this->stubInner(), $this->createStub(TokenStorageInterface::class), $accounts, $authority, $evidence);
    $user = new SecurityUser('actor', 'actor@example.com', 'hash');

    self::assertFalse($checker->isGrantedForUser($user, 'ROLE_USER'));
    self::assertFalse($checker->isGrantedForUser($user, 'ROLE_USER'));
  }

  #[Test]
  public function nonRoleUserChecksPreserveTheInstalledSymfonyEntryPoint(): void
  {
    $user = new SecurityUser('actor', 'actor@example.com', 'hash');
    $subject = new stdClass();
    $decision = new AccessDecision();
    $inner = $this->mockInner();
    $inner->expects(self::once())->method('isGrantedForUser')->with($user, 'EDIT', $subject, $decision)->willReturn(true);
    $accounts = $this->createMock(HttpIdempotencyAccountPort::class);
    $accounts->expects(self::never())->method('current');
    $checker = new EvidenceHttpRoleAuthorizationCheckerAdapter($inner, $this->createStub(TokenStorageInterface::class), $accounts, $this->createStub(HttpIdempotencyPlatformAuthorityPort::class), $this->createStub(HttpIdempotencyPlatformAuthorizationEvidencePort::class));

    self::assertTrue($checker->isGrantedForUser($user, 'EDIT', $subject, $decision));
  }

  #[Test]
  public function untrackedRoleRequestsPreserveTheOriginalNativeCheckerDecision(): void
  {
    $user = new SecurityUser('legacy-actor', 'actor@example.com', 'hash', ['ROLE_ADMIN']);
    $token = $this->createStub(TokenInterface::class);
    $token->method('getUser')->willReturn($user);
    $tokens = $this->createStub(TokenStorageInterface::class);
    $tokens->method('getToken')->willReturn($token);
    $inner = $this->mockInner();
    $inner->expects(self::once())->method('isGranted')->with('ROLE_ADMIN', null)->willReturn(true);
    $inner->expects(self::once())->method('isGrantedForUser')->with($user, 'ROLE_USER', null, self::isInstanceOf(AccessDecision::class))->willReturn(true);
    $accounts = $this->createMock(HttpIdempotencyAccountPort::class);
    $accounts->expects(self::never())->method('current');
    $evidence = $this->createMock(HttpIdempotencyPlatformAuthorizationEvidencePort::class);
    $evidence->expects(self::exactly(2))->method('tracksPrincipal')->with('legacy-actor')->willReturn(false);
    $evidence->expects(self::never())->method('recordPlatform');
    $checker = new EvidenceHttpRoleAuthorizationCheckerAdapter($inner, $tokens, $accounts, $this->createStub(HttpIdempotencyPlatformAuthorityPort::class), $evidence);
    $decision = new AccessDecision();

    self::assertTrue($checker->isGranted('ROLE_ADMIN', accessDecision: $decision));
    self::assertTrue($decision->isGranted);
    self::assertTrue($checker->isGrantedForUser($user, 'ROLE_USER', accessDecision: new AccessDecision()));
  }

  private function mockInner(): AuthorizationCheckerInterface&UserAuthorizationCheckerInterface&\PHPUnit\Framework\MockObject\MockObject
  {
    $inner = $this->createMockForIntersectionOfInterfaces([AuthorizationCheckerInterface::class, UserAuthorizationCheckerInterface::class]);
    self::assertInstanceOf(AuthorizationCheckerInterface::class, $inner);
    self::assertInstanceOf(UserAuthorizationCheckerInterface::class, $inner);

    return $inner;
  }

  private function stubInner(): AuthorizationCheckerInterface&UserAuthorizationCheckerInterface
  {
    $inner = $this->createStubForIntersectionOfInterfaces([AuthorizationCheckerInterface::class, UserAuthorizationCheckerInterface::class]);
    self::assertInstanceOf(AuthorizationCheckerInterface::class, $inner);
    self::assertInstanceOf(UserAuthorizationCheckerInterface::class, $inner);

    return $inner;
  }
}
