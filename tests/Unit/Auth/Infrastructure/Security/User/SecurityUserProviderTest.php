<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Security\User;

use Auth\Infrastructure\Security\User\{SecurityUser, SecurityUserProvider};
use Authorization\Application\Port\Inbound\AuthorizationPort;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
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

    $authorization = $this->createMock(AuthorizationPort::class);

    $provider = new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
    );

    $this->expectException(UserNotFoundException::class);
    $provider->loadUserById('missing-user');
  }

  /**
   * Method testRefreshUserThrowsForUnsupportedUser.
   */
  #[Test]
  public function testRefreshUserThrowsForUnsupportedUser(): void
  {
    $provider = new SecurityUserProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorizationService: $this->createMock(AuthorizationPort::class),
    );

    $this->expectException(UnsupportedUserException::class);
    $provider->refreshUser($this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class));
  }

  /**
   * Method testSupportsClassReturnsTrueForSecurityUser.
   */
  #[Test]
  public function testSupportsClassReturnsTrueForSecurityUser(): void
  {
    $provider = new SecurityUserProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorizationService: $this->createMock(AuthorizationPort::class),
    );

    $this->assertTrue($provider->supportsClass(SecurityUser::class));
    $this->assertFalse($provider->supportsClass(stdClass::class));
  }
  // #endregion
}
