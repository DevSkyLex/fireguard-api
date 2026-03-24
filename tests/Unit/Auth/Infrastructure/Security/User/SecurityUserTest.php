<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Security\User;

use Auth\Infrastructure\Security\User\SecurityUser;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test SecurityUserTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: SecurityUser::class)]
final class SecurityUserTest extends TestCase
{
  // #region Methods
  /**
   * Method testGetRolesAlwaysIncludesRoleUser.
   */
  #[Test]
  public function testGetRolesAlwaysIncludesRoleUser(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
      roles: ['ROLE_ADMIN'],
    );

    $roles = $user->getRoles();

    $this->assertContains('ROLE_USER', $roles);
    $this->assertContains('ROLE_ADMIN', $roles);
  }

  /**
   * Method testGetUserIdentifierReturnsEmail.
   */
  #[Test]
  public function testGetUserIdentifierReturnsEmail(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
    );

    $this->assertSame('user@example.com', $user->getUserIdentifier());
  }

  /**
   * Method testHasScopeIsCaseInsensitive.
   */
  #[Test]
  public function testHasScopeIsCaseInsensitive(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
      roles: ['ROLE_USER'],
      scopes: ['read', 'Write'],
    );

    $this->assertTrue($user->hasScope('READ'));
    $this->assertTrue($user->hasScope('write'));
    $this->assertFalse($user->hasScope('delete'));
  }

  /**
   * Method testIsActiveReturnsFlag.
   */
  #[Test]
  public function testIsActiveReturnsFlag(): void
  {
    $active = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );

    $inactive = new SecurityUser(
      id: 'user-456',
      email: 'inactive@example.com',
      password: 'hash',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: false,
    );

    $this->assertTrue($active->isActive());
    $this->assertFalse($inactive->isActive());
  }

  #[Test]
  public function testGetPasswordReturnsHash(): void
  {
    $user = new SecurityUser(
      id: 'user-789',
      email: 'user@example.com',
      password: 'hashed',
    );

    $this->assertSame('hashed', $user->getPassword());
  }

  #[Test]
  public function testGetTenantIdReturnsNullWhenNotSet(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
    );

    $this->assertNull($user->getTenantId());
  }

  #[Test]
  public function testGetTenantIdReturnsValue(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
      tenantId: 'tenant-abc',
    );

    $this->assertSame('tenant-abc', $user->getTenantId());
  }
  // #endregion
}
