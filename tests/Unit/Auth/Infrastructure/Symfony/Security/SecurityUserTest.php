<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Symfony\Security;

use Auth\Infrastructure\Symfony\Security\SecurityUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Class SecurityUserTest
 *
 * Unit tests for the SecurityUser adapter.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Infrastructure\Symfony\Security
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: SecurityUser::class)]
final class SecurityUserTest extends TestCase
{
  //#region Methods
  /**
   * Method testGetId
   *
   * Tests that getId returns the user ID.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testGetId(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: 'hashed_password'
    );

    $this->assertEquals(expected: 'user-123', actual: $user->getId());
  }

  /**
   * Method testGetUserIdentifier
   *
   * Tests that getUserIdentifier returns the email.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testGetUserIdentifier(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: 'hashed_password'
    );

    $this->assertEquals(expected: 'test@example.com', actual: $user->getUserIdentifier());
  }

  /**
   * Method testGetRolesIncludesRoleUser
   *
   * Tests that getRoles always includes ROLE_USER.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testGetRolesIncludesRoleUser(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: 'hashed_password',
      roles: []
    );

    $roles = $user->getRoles();

    $this->assertContains(needle: 'ROLE_USER', haystack: $roles);
  }

  /**
   * Method testGetRolesReturnsAllRoles
   *
   * Tests that getRoles returns all assigned roles.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testGetRolesReturnsAllRoles(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: 'hashed_password',
      roles: ['ROLE_ADMIN', 'ROLE_VERIFIED']
    );

    $roles = $user->getRoles();

    $this->assertContains(needle: 'ROLE_USER', haystack: $roles);
    $this->assertContains(needle: 'ROLE_ADMIN', haystack: $roles);
    $this->assertContains(needle: 'ROLE_VERIFIED', haystack: $roles);
  }

  /**
   * Method testGetRolesReturnsUniqueRoles
   *
   * Tests that getRoles returns unique roles.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testGetRolesReturnsUniqueRoles(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: 'hashed_password',
      roles: ['ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN']
    );

    $roles = $user->getRoles();

    $this->assertCount(expectedCount: 2, haystack: $roles);
  }

  /**
   * Method testGetScopes
   *
   * Tests that getScopes returns the OAuth2 scopes.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testGetScopes(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: 'hashed_password',
      roles: [],
      scopes: ['openid', 'profile', 'email']
    );

    $scopes = $user->getScopes();

    $this->assertCount(expectedCount: 3, haystack: $scopes);
    $this->assertContains(needle: 'openid', haystack: $scopes);
    $this->assertContains(needle: 'profile', haystack: $scopes);
    $this->assertContains(needle: 'email', haystack: $scopes);
  }

  /**
   * Method testHasScopeReturnsTrueForExistingScope
   *
   * Tests that hasScope returns true for an existing scope.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testHasScopeReturnsTrueForExistingScope(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: 'hashed_password',
      roles: [],
      scopes: ['openid', 'profile']
    );

    $this->assertTrue(condition: $user->hasScope(scope: 'openid'));
    $this->assertTrue(condition: $user->hasScope(scope: 'profile'));
  }

  /**
   * Method testHasScopeReturnsFalseForMissingScope
   *
   * Tests that hasScope returns false for a missing scope.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testHasScopeReturnsFalseForMissingScope(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: 'hashed_password',
      roles: [],
      scopes: ['openid']
    );

    $this->assertFalse(condition: $user->hasScope(scope: 'admin'));
  }

  /**
   * Method testHasScopeIsCaseInsensitive
   *
   * Tests that hasScope is case insensitive.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testHasScopeIsCaseInsensitive(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: 'hashed_password',
      roles: [],
      scopes: ['OPENID', 'Profile']
    );

    $this->assertTrue(condition: $user->hasScope(scope: 'openid'));
    $this->assertTrue(condition: $user->hasScope(scope: 'OPENID'));
    $this->assertTrue(condition: $user->hasScope(scope: 'profile'));
    $this->assertTrue(condition: $user->hasScope(scope: 'PROFILE'));
  }

  /**
   * Method testGetPassword
   *
   * Tests that getPassword returns the hashed password.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testGetPassword(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: 'hashed_password_value'
    );

    $this->assertEquals(expected: 'hashed_password_value', actual: $user->getPassword());
  }

  /**
   * Method testIsActive
   *
   * Tests that isActive returns the correct value.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  #[DataProvider(methodName: 'isActiveProvider')]
  public function testIsActive(bool $isActive): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: 'hashed_password',
      roles: [],
      scopes: [],
      isActive: $isActive
    );

    $this->assertEquals(expected: $isActive, actual: $user->isActive());
  }

  /**
   * Method isActiveProvider
   *
   * Data provider for testIsActive.
   *
   * @access public
   * @static
   *
   * @return iterable<string, array{bool}>
   */
  public static function isActiveProvider(): iterable
  {
    yield 'active user' => [true];
    yield 'inactive user' => [false];
  }

  /**
   * Method testEraseCredentials
   *
   * Tests that eraseCredentials does not throw.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testEraseCredentials(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: 'hashed_password'
    );

    // Should not throw - method is empty by design
    $user->eraseCredentials();

    // If we reach here, the method didn't throw
    $this->addToAssertionCount(1);
  }
  //#endregion
}
