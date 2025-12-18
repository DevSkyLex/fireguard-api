<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Aggregate;

use Auth\Domain\Aggregate\TokenSession;
use OAuth\Domain\Event\TokenIssuedEvent;
use OAuth\Domain\Event\TokenRevokedEvent;
use Auth\Domain\Event\UserLoggedInEvent;
use Auth\Domain\Event\UserLoggedOutEvent;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class TokenSessionTest
 *
 * Unit tests for the TokenSession aggregate.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Domain\Aggregate
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Domain\Aggregate\TokenSession
 */
#[CoversClass(className: TokenSession::class)]
final class TokenSessionTest extends TestCase
{
  //#region User Session Tests
  /**
   * Method testCanCreateUserSession
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanCreateUserSession(): void
  {
    $session = TokenSession::createForUser(
      userId: 'user-123',
      email: 'test@example.com',
      scopes: ['OPENID', 'PROFILE'],
      accessTokenTtl: 3600,
      refreshTokenTtl: 86400,
    );

    $this->assertEquals(expected: 'user-123', actual: $session->userId());
    $this->assertEquals(expected: ['OPENID', 'PROFILE'], actual: $session->scopes());
    $this->assertFalse(condition: $session->isRevoked());
    $this->assertFalse(condition: $session->isExpired());
    $this->assertNotEmpty(actual: (string) $session->accessTokenId());
    $this->assertNotEmpty(actual: (string) $session->refreshTokenId());
  }

  /**
   * Method testCreateUserSessionRecordsEvents
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCreateUserSessionRecordsEvents(): void
  {
    $session = TokenSession::createForUser(
      userId: 'user-123',
      email: 'test@example.com',
      scopes: ['OPENID'],
    );

    $events = $session->pullDomainEvents();

    $this->assertCount(expectedCount: 2, haystack: $events);
    $this->assertInstanceOf(expected: UserLoggedInEvent::class, actual: $events[0]);
    $this->assertInstanceOf(expected: TokenIssuedEvent::class, actual: $events[1]);
  }
  //#endregion

  //#region Client Session Tests
  /**
   * Method testCanCreateClientSession
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanCreateClientSession(): void
  {
    $session = TokenSession::createForClient(
      clientId: 'client-456',
      scopes: ['OPENID'],
      accessTokenTtl: 3600,
    );

    $this->assertEquals(expected: 'client-456', actual: $session->clientId());
    $this->assertEquals(expected: ['OPENID'], actual: $session->scopes());
    $this->assertFalse(condition: $session->isRevoked());
    $this->assertNotEmpty(actual: (string) $session->accessTokenId());
    $this->assertNull(actual: $session->refreshTokenId());
  }

  /**
   * Method testCreateClientSessionRecordsTokenIssuedEvent
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCreateClientSessionRecordsTokenIssuedEvent(): void
  {
    $session = TokenSession::createForClient(
      clientId: 'client-456',
      scopes: ['OPENID'],
    );

    $events = $session->pullDomainEvents();

    $this->assertCount(expectedCount: 1, haystack: $events);
    $this->assertInstanceOf(expected: TokenIssuedEvent::class, actual: $events[0]);
  }
  //#endregion

  //#region Revocation Tests
  /**
   * Method testCanRevokeUserSession
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanRevokeUserSession(): void
  {
    $session = TokenSession::createForUser(
      userId: 'user-123',
      email: 'test@example.com',
      scopes: ['OPENID'],
    );

    $session->pullDomainEvents(); // Clear creation events
    $session->revoke(reason: 'user_logout');

    $this->assertTrue(condition: $session->isRevoked());

    $events = $session->pullDomainEvents();
    // Access token revoked + refresh token revoked + user logged out
    $this->assertCount(expectedCount: 3, haystack: $events);
    $this->assertInstanceOf(expected: TokenRevokedEvent::class, actual: $events[0]);
    $this->assertInstanceOf(expected: TokenRevokedEvent::class, actual: $events[1]);
    $this->assertInstanceOf(expected: UserLoggedOutEvent::class, actual: $events[2]);
  }

  /**
   * Method testCanRevokeClientSession
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanRevokeClientSession(): void
  {
    $session = TokenSession::createForClient(
      clientId: 'client-456',
      scopes: ['OPENID'],
    );

    $session->pullDomainEvents();
    $session->revoke();

    $this->assertTrue(condition: $session->isRevoked());

    $events = $session->pullDomainEvents();
    // Only access token revoked (no refresh token, no user)
    $this->assertCount(expectedCount: 1, haystack: $events);
    $this->assertInstanceOf(expected: TokenRevokedEvent::class, actual: $events[0]);
  }

  /**
   * Method testRevokeIsIdempotent
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRevokeIsIdempotent(): void
  {
    $session = TokenSession::createForClient(
      clientId: 'client-456',
      scopes: ['OPENID'],
    );

    $session->pullDomainEvents();
    $session->revoke();
    $session->revoke(); // Second revoke should not add event

    $events = $session->pullDomainEvents();
    $this->assertCount(expectedCount: 1, haystack: $events);
  }
  //#endregion

  //#region Event Tests
  /**
   * Method testReleaseEventsClearsEvents
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testReleaseEventsClearsEvents(): void
  {
    $session = TokenSession::createForClient(
      clientId: 'client-456',
      scopes: ['OPENID'],
    );

    $events1 = $session->pullDomainEvents();
    $events2 = $session->pullDomainEvents();

    $this->assertCount(expectedCount: 1, haystack: $events1);
    $this->assertCount(expectedCount: 0, haystack: $events2);
  }
  //#endregion

  //#region Getters Tests
  /**
   * Method testCreatedAtIsSet
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCreatedAtIsSet(): void
  {
    $before = new \DateTimeImmutable();
    $session = TokenSession::createForUser(
      userId: 'user-123',
      email: 'test@example.com',
      scopes: ['OPENID'],
    );
    $after = new \DateTimeImmutable();

    $this->assertGreaterThanOrEqual($before, $session->createdAt());
    $this->assertLessThanOrEqual($after, $session->createdAt());
  }

  /**
   * Method testAccessTokenExpiryIsSet
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testAccessTokenExpiryIsSet(): void
  {
    $session = TokenSession::createForUser(
      userId: 'user-123',
      email: 'test@example.com',
      scopes: ['OPENID'],
      accessTokenTtl: 3600,
    );

    $expiry = $session->accessTokenExpiry();
    $this->assertFalse(condition: $expiry->isExpired());
    $this->assertGreaterThan(3500, $expiry->secondsRemaining());
  }

  /**
   * Method testRefreshTokenExpiryIsSetForUserSession
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRefreshTokenExpiryIsSetForUserSession(): void
  {
    $session = TokenSession::createForUser(
      userId: 'user-123',
      email: 'test@example.com',
      scopes: ['OPENID'],
      refreshTokenTtl: 86400,
    );

    $expiry = $session->refreshTokenExpiry();
    $this->assertNotNull(actual: $expiry);
    $this->assertFalse(condition: $expiry->isExpired());
  }

  /**
   * Method testRefreshTokenExpiryIsNullForClientSession
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRefreshTokenExpiryIsNullForClientSession(): void
  {
    $session = TokenSession::createForClient(
      clientId: 'client-456',
      scopes: ['OPENID'],
    );

    $this->assertNull(actual: $session->refreshTokenExpiry());
  }
  //#endregion
}
