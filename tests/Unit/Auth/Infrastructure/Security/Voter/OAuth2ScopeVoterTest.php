<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Security\Voter;

use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Infrastructure\Security\Voter\OAuth2ScopeVoter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test OAuth2ScopeVoterTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OAuth2ScopeVoter::class)]
final class OAuth2ScopeVoterTest extends TestCase
{
  // #region Methods
  /**
   * Method testVoteGrantsWhenScopePresent.
   */
  #[Test]
  public function testVoteGrantsWhenScopePresent(): void
  {
    $voter = new OAuth2ScopeVoter();

    $user = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
      roles: ['ROLE_USER'],
      scopes: ['read', 'write'],
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $voter->vote($token, null, ['SCOPE_READ']);

    $this->assertSame(Voter::ACCESS_GRANTED, $result);
  }

  /**
   * Method testVoteDeniesWhenScopeMissing.
   */
  #[Test]
  public function testVoteDeniesWhenScopeMissing(): void
  {
    $voter = new OAuth2ScopeVoter();

    $user = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
      roles: ['ROLE_USER'],
      scopes: ['read'],
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $voter->vote($token, null, ['SCOPE_DELETE']);

    $this->assertSame(Voter::ACCESS_DENIED, $result);
  }

  /**
   * Method testVoteDeniesWhenUserNotSecurityUser.
   */
  #[Test]
  public function testVoteDeniesWhenUserNotSecurityUser(): void
  {
    $voter = new OAuth2ScopeVoter();

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($this->createMock(UserInterface::class));

    $result = $voter->vote($token, null, ['SCOPE_READ']);

    $this->assertSame(Voter::ACCESS_DENIED, $result);
  }
  // #endregion
}
