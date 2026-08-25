<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Application\UseCase\Query\Session\GetSessionByAccessToken;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Query\Session\GetSessionByAccessToken\{GetSessionByAccessTokenHandler, GetSessionByAccessTokenQuery};
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\{SessionId, SessionMetadata};
use Shared\Domain\ValueObject\{IpAddress, UserAgent};

/**
 * Test GetSessionByAccessTokenHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: GetSessionByAccessTokenHandler::class)]
final class GetSessionByAccessTokenHandlerTest extends TestCase
{
  // #region Constants
  private const string SESSION_ID = '123e4567-e89b-12d3-a456-426614174000';
  // #endregion

  // #region Methods
  #[Test]
  public function testInvokeReportsAnActiveSessionAsTrackedAndNotRevoked(): void
  {
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByAccessTokenId')
      ->with('access-token-1')
      ->willReturn($this->createSession());

    $handler = new GetSessionByAccessTokenHandler(sessionRepository: $repository);
    $result = $handler->__invoke(new GetSessionByAccessTokenQuery(accessTokenId: 'access-token-1'));

    self::assertTrue($result->tracked);
    self::assertFalse($result->revoked);
    self::assertSame(self::SESSION_ID, $result->sessionId);
    self::assertSame('user-123', $result->userId);
  }

  #[Test]
  public function testInvokeReportsARevokedSessionAsRevoked(): void
  {
    $session = $this->createSession();
    $session->revoke();

    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByAccessTokenId')
      ->willReturn($session);

    $handler = new GetSessionByAccessTokenHandler(sessionRepository: $repository);
    $result = $handler->__invoke(new GetSessionByAccessTokenQuery(accessTokenId: 'access-token-1'));

    self::assertTrue($result->tracked);
    self::assertTrue($result->revoked);
  }

  #[Test]
  public function testInvokeReportsAnUnknownTokenAsUntrackedRatherThanThrowing(): void
  {
    // The authenticator calls this on every request; a missing row is an
    // expected outcome there, not an exceptional one.
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByAccessTokenId')
      ->willReturn(null);

    $handler = new GetSessionByAccessTokenHandler(sessionRepository: $repository);
    $result = $handler->__invoke(new GetSessionByAccessTokenQuery(accessTokenId: 'unknown'));

    self::assertFalse($result->tracked);
    self::assertFalse($result->revoked);
    self::assertNull($result->sessionId);
    self::assertNull($result->userId);
  }

  #[Test]
  public function testInvokeDoesNotQueryOnAnEmptyIdentifier(): void
  {
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::never())->method('findByAccessTokenId');

    $handler = new GetSessionByAccessTokenHandler(sessionRepository: $repository);
    $result = $handler->__invoke(new GetSessionByAccessTokenQuery(accessTokenId: ''));

    self::assertFalse($result->tracked);
  }
  // #endregion

  // #region Helpers
  private function createSession(): Session
  {
    return Session::create(
      id: new SessionId(self::SESSION_ID),
      userId: 'user-123',
      ipAddress: new IpAddress('192.168.1.1'),
      userAgent: new UserAgent('Mozilla/5.0'),
      metadata: new SessionMetadata(deviceType: 'desktop'),
      accessTokenId: 'access-token-1',
      refreshTokenId: 'refresh-token-1',
    );
  }
  // #endregion
}
