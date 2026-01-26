<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Application\UseCase\Command\Session\RevokeSessionByToken;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Command\Session\RevokeSessionByToken\{RevokeSessionByTokenCommand, RevokeSessionByTokenHandler, RevokeSessionByTokenResult};
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\SessionId;
use Shared\Domain\ValueObject\{IpAddress, UserAgent};

/**
 * Test RevokeSessionByTokenHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RevokeSessionByTokenHandler::class)]
final class RevokeSessionByTokenHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeRevokesUsingRefreshToken(): void
  {
    $session = Session::create(
      id: new SessionId('550e8400-e29b-41d4-a716-446655440000'),
      userId: 'user-1',
      ipAddress: new IpAddress('127.0.0.1'),
      userAgent: new UserAgent('Mozilla/5.0'),
      accessTokenId: 'access-1',
      refreshTokenId: 'refresh-1',
    );

    /** @var SessionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByRefreshTokenId')
      ->with('refresh-1')
      ->willReturn($session);
    $repository->expects(self::once())
      ->method('save')
      ->with($session);

    $handler = new RevokeSessionByTokenHandler($repository);

    $result = $handler->__invoke(new RevokeSessionByTokenCommand(
      refreshTokenId: 'refresh-1',
      accessTokenId: null,
    ));

    self::assertInstanceOf(RevokeSessionByTokenResult::class, $result);
    self::assertTrue($result->revoked);
  }

  #[Test]
  public function testInvokeFallsBackToAccessToken(): void
  {
    $session = Session::create(
      id: new SessionId('550e8400-e29b-41d4-a716-446655440001'),
      userId: 'user-2',
      ipAddress: new IpAddress('127.0.0.1'),
      userAgent: new UserAgent('Mozilla/5.0'),
      accessTokenId: 'access-2',
      refreshTokenId: 'refresh-2',
    );

    /** @var SessionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByRefreshTokenId')
      ->with('refresh-2')
      ->willReturn(null);
    $repository->expects(self::once())
      ->method('findByAccessTokenId')
      ->with('access-2')
      ->willReturn($session);
    $repository->expects(self::once())
      ->method('save')
      ->with($session);

    $handler = new RevokeSessionByTokenHandler($repository);

    $result = $handler->__invoke(new RevokeSessionByTokenCommand(
      refreshTokenId: 'refresh-2',
      accessTokenId: 'access-2',
    ));

    self::assertTrue($result->revoked);
    self::assertSame($session->id()->value, $result->sessionId);
  }

  #[Test]
  public function testInvokeReturnsFalseWhenMissing(): void
  {
    /** @var SessionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByRefreshTokenId')
      ->willReturn(null);
    $repository->expects(self::once())
      ->method('findByAccessTokenId')
      ->willReturn(null);

    $handler = new RevokeSessionByTokenHandler($repository);

    $result = $handler->__invoke(new RevokeSessionByTokenCommand(
      refreshTokenId: 'refresh-missing',
      accessTokenId: 'access-missing',
    ));

    self::assertFalse($result->revoked);
    self::assertNull($result->sessionId);
  }
  // #endregion
}
