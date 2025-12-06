<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Service;

use Auth\Domain\Model\AccessToken;
use Auth\Domain\Model\RefreshToken;
use Auth\Domain\Service\TokenValidationResult;
use Auth\Domain\Service\TokenValidationService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\ValueObject\OAuthClientIdentifier;
use Shared\Domain\ValueObject\Scopes;

/**
 * Class TokenValidationServiceTest
 *
 * Unit tests for the TokenValidationService.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Domain\Service
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Domain\Service\TokenValidationService
 */
#[CoversClass(className: TokenValidationService::class)]
#[CoversClass(className: TokenValidationResult::class)]
final class TokenValidationServiceTest extends TestCase
{
  //#region Properties
  private TokenValidationService $service;
  //#endregion

  //#region Setup
  protected function setUp(): void
  {
    $this->service = new TokenValidationService();
  }
  //#endregion

  //#region Access Token Tests
  /**
   * Method testValidateAccessTokenReturnsNotFoundWhenNull
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testValidateAccessTokenReturnsNotFoundWhenNull(): void
  {
    $result = $this->service->validateAccessToken(token: null);

    $this->assertFalse(condition: $result->valid);
    $this->assertEquals(
      expected: TokenValidationService::VALIDATION_NOT_FOUND,
      actual: $result->errorCode
    );
  }

  /**
   * Method testValidateAccessTokenReturnsRevokedWhenRevoked
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testValidateAccessTokenReturnsRevokedWhenRevoked(): void
  {
    $token = $this->createAccessToken(revoked: true, expired: false);
    $result = $this->service->validateAccessToken(token: $token);

    $this->assertFalse(condition: $result->valid);
    $this->assertEquals(
      expected: TokenValidationService::VALIDATION_REVOKED,
      actual: $result->errorCode
    );
    $this->assertTrue(condition: $result->isRevoked());
  }

  /**
   * Method testValidateAccessTokenReturnsExpiredWhenExpired
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testValidateAccessTokenReturnsExpiredWhenExpired(): void
  {
    $token = $this->createAccessToken(revoked: false, expired: true);
    $result = $this->service->validateAccessToken(token: $token);

    $this->assertFalse(condition: $result->valid);
    $this->assertEquals(
      expected: TokenValidationService::VALIDATION_EXPIRED,
      actual: $result->errorCode
    );
    $this->assertTrue(condition: $result->isExpired());
  }

  /**
   * Method testValidateAccessTokenReturnsSuccessWhenValid
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testValidateAccessTokenReturnsSuccessWhenValid(): void
  {
    $token = $this->createAccessToken(revoked: false, expired: false);
    $result = $this->service->validateAccessToken(token: $token);

    $this->assertTrue(condition: $result->valid);
    $this->assertNull(actual: $result->errorCode);
    $this->assertEquals(expected: 'test-token-id', actual: $result->tokenId);
    $this->assertEquals(expected: 'user-123', actual: $result->userId);
    $this->assertEquals(expected: 'client-123', actual: $result->clientId);
  }

  /**
   * Method testValidateAccessTokenChecksRequiredScopes
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testValidateAccessTokenChecksRequiredScopes(): void
  {
    $token = $this->createAccessToken(revoked: false, expired: false, scopes: ['OPENID', 'PROFILE']);

    // Valid scopes
    $result = $this->service->validateAccessToken(token: $token, requiredScopes: ['OPENID']);
    $this->assertTrue(condition: $result->valid);

    // Missing scope
    $result = $this->service->validateAccessToken(token: $token, requiredScopes: ['EMAIL']);
    $this->assertFalse(condition: $result->valid);
    $this->assertEquals(
      expected: TokenValidationService::VALIDATION_INVALID_SCOPE,
      actual: $result->errorCode
    );
  }
  //#endregion

  //#region Refresh Token Tests
  /**
   * Method testValidateRefreshTokenReturnsNotFoundWhenNull
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testValidateRefreshTokenReturnsNotFoundWhenNull(): void
  {
    $result = $this->service->validateRefreshToken(token: null);

    $this->assertFalse(condition: $result->valid);
    $this->assertEquals(
      expected: TokenValidationService::VALIDATION_NOT_FOUND,
      actual: $result->errorCode
    );
  }

  /**
   * Method testValidateRefreshTokenReturnsRevokedWhenRevoked
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testValidateRefreshTokenReturnsRevokedWhenRevoked(): void
  {
    $token = $this->createRefreshToken(revoked: true, expired: false);
    $result = $this->service->validateRefreshToken(token: $token);

    $this->assertFalse(condition: $result->valid);
    $this->assertEquals(
      expected: TokenValidationService::VALIDATION_REVOKED,
      actual: $result->errorCode
    );
  }

  /**
   * Method testValidateRefreshTokenReturnsExpiredWhenExpired
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testValidateRefreshTokenReturnsExpiredWhenExpired(): void
  {
    $token = $this->createRefreshToken(revoked: false, expired: true);
    $result = $this->service->validateRefreshToken(token: $token);

    $this->assertFalse(condition: $result->valid);
    $this->assertEquals(
      expected: TokenValidationService::VALIDATION_EXPIRED,
      actual: $result->errorCode
    );
  }

  /**
   * Method testValidateRefreshTokenReturnsSuccessWhenValid
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testValidateRefreshTokenReturnsSuccessWhenValid(): void
  {
    $token = $this->createRefreshToken(revoked: false, expired: false);
    $result = $this->service->validateRefreshToken(token: $token);

    $this->assertTrue(condition: $result->valid);
    $this->assertNull(actual: $result->errorCode);
  }
  //#endregion

  //#region Can Refresh Tests
  /**
   * Method testCanRefreshReturnsFalseWhenNull
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanRefreshReturnsFalseWhenNull(): void
  {
    $this->assertFalse(condition: $this->service->canRefresh(token: null));
  }

  /**
   * Method testCanRefreshReturnsFalseWhenRevoked
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanRefreshReturnsFalseWhenRevoked(): void
  {
    $token = $this->createRefreshToken(revoked: true, expired: false);
    $this->assertFalse(condition: $this->service->canRefresh(token: $token));
  }

  /**
   * Method testCanRefreshReturnsFalseWhenExpired
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanRefreshReturnsFalseWhenExpired(): void
  {
    $token = $this->createRefreshToken(revoked: false, expired: true);
    $this->assertFalse(condition: $this->service->canRefresh(token: $token));
  }

  /**
   * Method testCanRefreshReturnsTrueWhenValid
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanRefreshReturnsTrueWhenValid(): void
  {
    $token = $this->createRefreshToken(revoked: false, expired: false);
    $this->assertTrue(condition: $this->service->canRefresh(token: $token));
  }
  //#endregion

  //#region Helpers
  /**
   * @param list<string> $scopes
   */
  private function createAccessToken(
    bool $revoked,
    bool $expired,
    array $scopes = ['OPENID', 'PROFILE'],
  ): AccessToken {
    $expiry = $expired
      ? new DateTimeImmutable('-1 hour')
      : new DateTimeImmutable('+1 hour');

    $token = new AccessToken(
      identifier: 'test-token-id',
      expiry: $expiry,
      userIdentifier: 'user-123',
      clientIdentifier: new OAuthClientIdentifier(value: 'client-123'),
      scopes: Scopes::fromArray(scopes: $scopes),
    );

    if ($revoked) {
      $token->revoke();
    }

    return $token;
  }

  private function createRefreshToken(bool $revoked, bool $expired): RefreshToken
  {
    $expiry = $expired
      ? new DateTimeImmutable('-1 hour')
      : new DateTimeImmutable('+1 day');

    $token = new RefreshToken(
      identifier: 'refresh-token-id',
      expiryDateTime: $expiry,
      accessTokenIdentifier: 'access-token-id',
      clientIdentifier: new OAuthClientIdentifier(value: 'client-123'),
      isRevoked: $revoked,
    );

    return $token;
  }
  //#endregion
}
