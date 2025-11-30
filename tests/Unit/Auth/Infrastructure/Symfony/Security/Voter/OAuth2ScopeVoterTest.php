<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Symfony\Security\Voter;

use Auth\Infrastructure\Symfony\Security\SecurityUser;
use Auth\Infrastructure\Symfony\Security\Voter\OAuth2ScopeVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Class OAuth2ScopeVoterTest
 *
 * Unit tests for the OAuth2ScopeVoter.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Infrastructure\Symfony\Security\Voter
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OAuth2ScopeVoter::class)]
final class OAuth2ScopeVoterTest extends TestCase
{
  //#region Properties
  /**
   * Property voter
   *
   * OAuth2ScopeVoter instance.
   *
   * @access private
   *
   * @var OAuth2ScopeVoter
   */
  private OAuth2ScopeVoter $voter;
  //#endregion

  //#region Methods
  /**
   * Method setUp
   *
   * Sets up the test environment.
   *
   * @access protected
   *
   * @return void
   */
  protected function setUp(): void
  {
    $this->voter = new OAuth2ScopeVoter();
  }

  /**
   * Method testVoteGrantsAccessWhenUserHasScope
   *
   * Tests that vote grants access when user has the required scope.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testVoteGrantsAccessWhenUserHasScope(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: '',
      roles: ['ROLE_USER'],
      scopes: ['read', 'write']
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $this->voter->vote($token, null, ['SCOPE_READ']);

    $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
  }

  /**
   * Method testVoteDeniesAccessWhenUserLacksScope
   *
   * Tests that vote denies access when user lacks the required scope.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testVoteDeniesAccessWhenUserLacksScope(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: '',
      roles: ['ROLE_USER'],
      scopes: ['read']
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $this->voter->vote($token, null, ['SCOPE_ADMIN']);

    $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
  }

  /**
   * Method testVoteAbstainsForNonScopeAttributes
   *
   * Tests that vote abstains for non-scope attributes.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testVoteAbstainsForNonScopeAttributes(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: '',
      roles: ['ROLE_USER'],
      scopes: ['read']
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $this->voter->vote($token, null, ['ROLE_ADMIN']);

    $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
  }

  /**
   * Method testVoteDeniesAccessWhenUserIsNotSecurityUser
   *
   * Tests that vote denies access when user is not a SecurityUser.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testVoteDeniesAccessWhenUserIsNotSecurityUser(): void
  {
    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn(null);

    $result = $this->voter->vote($token, null, ['SCOPE_READ']);

    $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
  }

  /**
   * Method testVoteIsCaseInsensitive
   *
   * Tests that scope matching is case insensitive.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  #[DataProvider('scopeCaseProvider')]
  public function testVoteIsCaseInsensitive(string $userScope, string $attribute): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: '',
      roles: ['ROLE_USER'],
      scopes: [$userScope]
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $this->voter->vote($token, null, [$attribute]);

    $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
  }

  /**
   * Method scopeCaseProvider
   *
   * Data provider for case insensitivity tests.
   *
   * @access public
   * @static
   *
   * @return iterable<string, array{string, string}>
   */
  public static function scopeCaseProvider(): iterable
  {
    yield 'lowercase scope, uppercase attribute' => ['read', 'SCOPE_READ'];
    yield 'uppercase scope, lowercase attribute' => ['READ', 'SCOPE_read'];
    yield 'mixed case scope' => ['ReAd', 'SCOPE_READ'];
    yield 'openid scope' => ['openid', 'SCOPE_OPENID'];
  }

  /**
   * Method testVoteWithMultipleAttributes
   *
   * Tests voting with multiple attributes.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testVoteWithMultipleAttributes(): void
  {
    $user = new SecurityUser(
      id: 'user-123',
      email: 'test@example.com',
      password: '',
      roles: ['ROLE_USER'],
      scopes: ['read', 'write']
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    // Should grant if at least one matches
    $result = $this->voter->vote($token, null, ['SCOPE_READ', 'SCOPE_ADMIN']);

    $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
  }
  //#endregion
}
