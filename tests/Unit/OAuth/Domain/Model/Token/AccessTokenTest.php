<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Model\Token;

use DateTimeImmutable;
use OAuth\Domain\Model\Token\AccessToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scope;
use OAuth\Domain\ValueObject\Scope\Scopes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Class AccessTokenTest.
 *
 * Unit tests for the AccessToken entity.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \OAuth\Domain\Model\Token\AccessToken
 */
#[CoversClass(className: AccessToken::class)]
final class AccessTokenTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanCreateAccessToken.
   *
   * Tests that an AccessToken can be created
   * with valid values.
   *
   * @return void no return value
   */
  #[Test]
  public function testCanCreateAccessToken(): void
  {
    $identifier = 'test_token_id';
    $clientIdentifier = new OAuthClientIdentifier(value: 'client_id');
    $expiry = new DateTimeImmutable(datetime: '+1 hour');
    $scopes = new Scopes(scopes: Scope::READ);
    $userIdentifier = 'user_id';

    $accessToken = new AccessToken(
      identifier: $identifier,
      clientIdentifier: $clientIdentifier,
      expiry: $expiry,
      scopes: $scopes,
      userIdentifier: $userIdentifier,
    );

    $this->assertEquals(
      expected: $identifier,
      actual: $accessToken->identifier(),
    );

    $this->assertEquals(
      expected: $clientIdentifier,
      actual: $accessToken->clientIdentifier(),
    );

    $this->assertEquals(
      expected: $expiry,
      actual: $accessToken->expiry(),
    );

    $this->assertEquals(
      expected: $scopes,
      actual: $accessToken->scopes(),
    );

    $this->assertEquals(
      expected: $userIdentifier,
      actual: $accessToken->userIdentifier(),
    );

    $this->assertFalse(condition: $accessToken->isRevoked());
    $this->assertFalse(condition: $accessToken->isExpired());
  }

  /**
   * Method testCanRevokeAccessToken.
   *
   * Tests that an AccessToken can be revoked.
   *
   * @return void no return value
   */
  #[Test]
  public function testCanRevokeAccessToken(): void
  {
    $accessToken = new AccessToken(
      identifier: 'test_token_id',
      clientIdentifier: new OAuthClientIdentifier(value: 'client_id'),
      expiry: new DateTimeImmutable(datetime: '+1 hour'),
      scopes: new Scopes(scopes: Scope::READ),
    );

    $accessToken->revoke();

    $this->assertTrue(condition: $accessToken->isRevoked());
  }

  /**
   * Method testIsExpiredReturnsTrueWhenExpired.
   *
   * Tests that isExpired returns true when the
   * token expiry date is in the past.
   *
   * @return void no return value
   */
  #[Test]
  public function testIsExpiredReturnsTrueWhenExpired(): void
  {
    $accessToken = new AccessToken(
      identifier: 'test_token_id',
      clientIdentifier: new OAuthClientIdentifier(value: 'client_id'),
      expiry: new DateTimeImmutable(datetime: '-1 hour'),
      scopes: new Scopes(scopes: Scope::READ),
    );

    $this->assertTrue(condition: $accessToken->isExpired());
  }
  // #endregion
}
