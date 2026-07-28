<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Security\User;

use Auth\Infrastructure\Security\User\{SecurityUser, SecurityUserProvider};
use Authorization\Application\Port\Inbound\AuthorizationPort;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CachePort;
use stdClass;
use Symfony\Component\Security\Core\Exception\{UnsupportedUserException, UserNotFoundException};
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

/**
 * Test SecurityUserProviderTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: SecurityUserProvider::class)]
final class SecurityUserProviderTest extends TestCase
{
  // #region Methods
  /**
   * Method testLoadUserByIdReturnsSecurityUser.
   */
  #[Test]
  public function testLoadUserByIdReturnsSecurityUser(): void
  {
    $userView = new UserView(
      id: 'user-123',
      username: 'user',
      email: 'user@example.com',
      firstName: 'User',
      lastName: 'Test',
      avatarUrl: null,
      status: 'active',
      emailVerified: true,
      tenantId: null,
      createdAt: new DateTimeImmutable(),
      lastLoginAt: null,
      canLogin: true,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetUserQuery::class))
      ->willReturn(new GetUserResult(user: $userView));

    /** @var AuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(AuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('getUserRoleNames')
      ->with('user-123')
      ->willReturn(['admin', 'manager']);

    $provider = new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
    );

    $user = $provider->loadUserById('user-123', ['read']);

    $this->assertInstanceOf(SecurityUser::class, $user);
    $this->assertSame('user-123', $user->getId());
    $this->assertSame('user@example.com', $user->getUserIdentifier());
    $this->assertTrue($user->isActive());
    $this->assertContains('ROLE_ADMIN', $user->getRoles());
    $this->assertContains('ROLE_MANAGER', $user->getRoles());
    $this->assertContains('ROLE_VERIFIED', $user->getRoles());
    $this->assertNull($user->getTenantId());
  }

  #[Test]
  public function testLoadUserByIdentifierDelegatesToLoadUserById(): void
  {
    $userView = $this->createUserView(canLogin: true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetUserQuery::class))
      ->willReturn(new GetUserResult(user: $userView));

    /** @var AuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(AuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('getUserRoleNames')
      ->with('user-123')
      ->willReturn([]);

    $provider = new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
    );

    $user = $provider->loadUserByIdentifier('user-123');

    $this->assertSame('user-123', $user->getId());
  }

  /**
   * Method testLoadUserByIdThrowsWhenUserMissing.
   */
  #[Test]
  public function testLoadUserByIdThrowsWhenUserMissing(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetUserResult(user: null));

    $authorization = $this->createStub(AuthorizationPort::class);

    $provider = new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
    );

    $this->expectException(UserNotFoundException::class);
    $provider->loadUserById('missing-user');
  }

  #[Test]
  public function testLoadUserByIdThrowsWhenQueryFails(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(new RuntimeException('boom'));

    $authorization = $this->createStub(AuthorizationPort::class);

    $provider = new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
    );

    $this->expectException(UserNotFoundException::class);
    $provider->loadUserById('user-123');
  }

  #[Test]
  public function testLoadUserByIdMapsInactiveUserRoles(): void
  {
    $userView = $this->createUserView(canLogin: false);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetUserResult(user: $userView));

    /** @var AuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(AuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('getUserRoleNames')
      ->willReturn([]);

    $provider = new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
    );

    $user = $provider->loadUserById('user-123');

    $this->assertContains('ROLE_USER', $user->getRoles());
    $this->assertNotContains('ROLE_VERIFIED', $user->getRoles());
  }

  /**
   * Method testRefreshUserThrowsForUnsupportedUser.
   */
  #[Test]
  public function testRefreshUserThrowsForUnsupportedUser(): void
  {
    $provider = new SecurityUserProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorizationService: $this->createStub(AuthorizationPort::class),
    );

    $this->expectException(UnsupportedUserException::class);
    $provider->refreshUser($this->createStub(\Symfony\Component\Security\Core\User\UserInterface::class));
  }

  #[Test]
  public function testRefreshUserReloadsSecurityUser(): void
  {
    $userView = $this->createUserView(canLogin: true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetUserQuery::class))
      ->willReturn(new GetUserResult(user: $userView));

    /** @var AuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(AuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('getUserRoleNames')
      ->with('user-123')
      ->willReturn([]);

    $provider = new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
    );

    $securityUser = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hashed',
      roles: ['ROLE_USER'],
      scopes: ['read'],
      isActive: true,
    );

    $refreshed = $provider->refreshUser($securityUser);

    $this->assertInstanceOf(SecurityUser::class, $refreshed);
    $this->assertSame('user-123', $refreshed->getId());
  }

  /**
   * Method testSupportsClassReturnsTrueForSecurityUser.
   */
  #[Test]
  public function testSupportsClassReturnsTrueForSecurityUser(): void
  {
    $provider = new SecurityUserProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorizationService: $this->createStub(AuthorizationPort::class),
    );

    $this->assertTrue($provider->supportsClass(SecurityUser::class));
    $this->assertFalse($provider->supportsClass(stdClass::class));
  }

  #[Test]
  public function testLoadUserByIdNormalizesEmptyStringTenantIdToNull(): void
  {
    $userView = new UserView(
      id: 'user-123',
      username: 'user',
      email: 'user@example.com',
      firstName: 'User',
      lastName: 'Test',
      avatarUrl: null,
      status: 'active',
      emailVerified: true,
      tenantId: '',
      createdAt: new DateTimeImmutable(),
      lastLoginAt: null,
      canLogin: true,
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetUserResult(user: $userView));

    $authorization = $this->createStub(AuthorizationPort::class);
    $authorization->method('getUserRoleNames')->willReturn([]);

    $provider = new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
    );

    $user = $provider->loadUserById('user-123');

    $this->assertNull($user->getTenantId());
  }

  #[Test]
  public function testLoadUserByIdPropagatesTenantId(): void
  {
    $userView = new UserView(
      id: 'user-123',
      username: 'user',
      email: 'user@example.com',
      firstName: 'User',
      lastName: 'Test',
      avatarUrl: null,
      status: 'active',
      emailVerified: true,
      tenantId: 'tenant-abc',
      createdAt: new DateTimeImmutable(),
      lastLoginAt: null,
      canLogin: true,
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetUserResult(user: $userView));

    $authorization = $this->createStub(AuthorizationPort::class);
    $authorization->method('getUserRoleNames')->willReturn([]);

    $provider = new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
    );

    $user = $provider->loadUserById('user-123');

    $this->assertSame('tenant-abc', $user->getTenantId());
  }

  #[Test]
  public function testLoadUserByIdUsesCachedBaseUserButAppliesCurrentTokenScopes(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $authorization = $this->createMock(AuthorizationPort::class);
    $authorization->expects(self::never())->method('getUserRoleNames');

    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('get')
      ->with('auth.security_user.user-123')
      ->willReturn([
        'id' => 'user-123',
        'email' => 'user@example.com',
        'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
        'isActive' => true,
        'tenantId' => null,
      ]);
    $cache->expects(self::never())->method('set');

    $provider = new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
      cache: $cache,
    );

    $user = $provider->loadUserById('user-123', ['fresh.scope']);

    self::assertSame(['fresh.scope'], $user->getScopes());
    self::assertContains('ROLE_ADMIN', $user->getRoles());
  }

  #[Test]
  public function testLoadUserByIdFallsBackToQueryBusWhenCacheReadThrows(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetUserResult(user: $this->createUserView(canLogin: true)));

    $authorization = $this->createStub(AuthorizationPort::class);
    $authorization->method('getUserRoleNames')->willReturn([]);

    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('get')
      ->willThrowException(new RuntimeException('cache down'));
    $cache->expects(self::once())->method('set');

    $provider = new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
      cache: $cache,
    );

    self::assertSame('user-123', $provider->loadUserById('user-123')->getId());
  }

  #[Test]
  public function testLoadUserByIdIgnoresMalformedCachePayload(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetUserResult(user: $this->createUserView(canLogin: true)));

    $authorization = $this->createStub(AuthorizationPort::class);
    $authorization->method('getUserRoleNames')->willReturn([]);

    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('get')
      ->willReturn([
        'id' => 'user-123',
        'email' => 'user@example.com',
        'roles' => ['ROLE_USER'],
        'isActive' => 'yes-please',
      ]);
    $cache->expects(self::once())->method('set');

    $provider = new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
      cache: $cache,
    );

    self::assertSame('user-123', $provider->loadUserById('user-123')->getId());
  }

  #[Test]
  public function testLoadUserByIdSwallowsCacheWriteFailures(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetUserResult(user: $this->createUserView(canLogin: true)));

    $authorization = $this->createStub(AuthorizationPort::class);
    $authorization->method('getUserRoleNames')->willReturn([]);

    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())->method('get')->willReturn(null);
    $cache->expects(self::once())
      ->method('set')
      ->willThrowException(new RuntimeException('cache write failed'));

    $provider = new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
      cache: $cache,
    );

    self::assertSame('user-123', $provider->loadUserById('user-123')->getId());
  }

  private function createUserView(bool $canLogin): UserView
  {
    return new UserView(
      id: 'user-123',
      username: 'user',
      email: 'user@example.com',
      firstName: 'User',
      lastName: 'Test',
      avatarUrl: null,
      status: 'active',
      emailVerified: true,
      tenantId: null,
      createdAt: new DateTimeImmutable(),
      lastLoginAt: null,
      canLogin: $canLogin,
    );
  }
  // #endregion
}
