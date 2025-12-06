<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Domain\Model;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Session\Domain\Model\Session;
use Session\Domain\ValueObject\SessionId;
use Shared\Domain\ValueObject\IpAddress;
use Shared\Domain\ValueObject\UserAgent;

/**
 * Class SessionTest
 *
 * Unit tests for the Session Model.
 *
 * @category Unit Test
 * @package Tests\Unit\Session\Domain\Model
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: Session::class)]
final class SessionTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanCreateSession
   *
   * Tests that a Session can be created.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanCreateSession(): void
  {
    $id = new SessionId('550e8400-e29b-41d4-a716-446655440000');
    $userId = 'user-123';
    $ipAddress = new IpAddress('192.168.1.1');
    $userAgent = new UserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    $session = Session::create($id, $userId, $ipAddress, $userAgent);

    $this->assertSame(expected: $id, actual: $session->id());
    $this->assertEquals(expected: $userId, actual: $session->userId());
    $this->assertSame(expected: $ipAddress, actual: $session->ipAddress());
    $this->assertSame(expected: $userAgent, actual: $session->userAgent());
    $this->assertNull(actual: $session->accessTokenId());
    $this->assertNull(actual: $session->refreshTokenId());
    $this->assertFalse(condition: $session->isRevoked());
  }

  /**
   * Method testCanUpdateTokens
   *
   * Tests that tokens can be updated.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanUpdateTokens(): void
  {
    $session = $this->createSession();
    $originalLastActivity = $session->lastActivityAt();

    // Small delay to ensure timestamp changes
    usleep(1000);

    $session->updateTokens('access-token-123', 'refresh-token-456');

    $this->assertEquals(expected: 'access-token-123', actual: $session->accessTokenId());
    $this->assertEquals(expected: 'refresh-token-456', actual: $session->refreshTokenId());
    $this->assertGreaterThanOrEqual($originalLastActivity, $session->lastActivityAt());
  }

  /**
   * Method testCanTouchSession
   *
   * Tests that the last activity can be updated.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanTouchSession(): void
  {
    $session = $this->createSession();
    $originalLastActivity = $session->lastActivityAt();

    usleep(1000);

    $session->touch();

    $this->assertGreaterThanOrEqual($originalLastActivity, $session->lastActivityAt());
  }

  /**
   * Method testCanRevokeSession
   *
   * Tests that a session can be revoked.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanRevokeSession(): void
  {
    $session = $this->createSession();

    $this->assertFalse(condition: $session->isRevoked());
    $this->assertNull(actual: $session->revokedAt());

    $session->revoke();

    $this->assertTrue(condition: $session->isRevoked());
    $this->assertNotNull(actual: $session->revokedAt());
  }

  /**
   * Method testRevokingAlreadyRevokedSessionIsNoOp
   *
   * Tests that revoking an already revoked session does nothing.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRevokingAlreadyRevokedSessionIsNoOp(): void
  {
    $session = $this->createSession();
    $session->revoke();

    $revokedAt = $session->revokedAt();

    $session->revoke();

    $this->assertEquals(expected: $revokedAt, actual: $session->revokedAt());
  }

  /**
   * Method testMetadataIsInitialized
   *
   * Tests that metadata is properly initialized.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testMetadataIsInitialized(): void
  {
    $session = $this->createSession();

    $this->assertFalse(condition: $session->metadata()->rememberMe);
  }

  /**
   * Method createSession
   *
   * Helper method to create a session for testing.
   *
   * @access private
   *
   * @return Session
   */
  private function createSession(): Session
  {
    return Session::create(
      id: new SessionId('550e8400-e29b-41d4-a716-446655440001'),
      userId: 'test-user-123',
      ipAddress: new IpAddress('127.0.0.1'),
      userAgent: new UserAgent('Test User Agent'),
    );
  }
  //#endregion
}
