<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Application\UseCase\Command\Session\UpdateSessionTokens;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Command\Session\UpdateSessionTokens\{UpdateSessionTokensCommand, UpdateSessionTokensHandler, UpdateSessionTokensResult};
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\SessionId;
use Shared\Domain\ValueObject\{IpAddress, UserAgent};

/**
 * Test UpdateSessionTokensHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateSessionTokensHandler::class)]
final class UpdateSessionTokensHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeUpdatesTokensByRefreshToken(): void
  {
    $session = Session::create(
      id: new SessionId('550e8400-e29b-41d4-a716-446655440010'),
      userId: 'user-1',
      ipAddress: new IpAddress('127.0.0.1'),
      userAgent: new UserAgent('Mozilla/5.0'),
      accessTokenId: 'access-old',
      refreshTokenId: 'refresh-old',
    );

    /** @var SessionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByRefreshTokenId')
      ->with('refresh-old')
      ->willReturn($session);
    $repository->expects(self::once())
      ->method('save')
      ->with($session);

    $handler = new UpdateSessionTokensHandler($repository);

    $result = $handler->__invoke(new UpdateSessionTokensCommand(
      currentRefreshTokenId: 'refresh-old',
      currentAccessTokenId: null,
      newAccessTokenId: 'access-new',
      newRefreshTokenId: 'refresh-new',
    ));

    self::assertInstanceOf(UpdateSessionTokensResult::class, $result);
    self::assertTrue($result->updated);
  }

  #[Test]
  public function testInvokeFallsBackToAccessToken(): void
  {
    $session = Session::create(
      id: new SessionId('550e8400-e29b-41d4-a716-446655440011'),
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
      ->with('refresh-missing')
      ->willReturn(null);
    $repository->expects(self::once())
      ->method('findByAccessTokenId')
      ->with('access-2')
      ->willReturn($session);
    $repository->expects(self::once())
      ->method('save')
      ->with($session);

    $handler = new UpdateSessionTokensHandler($repository);

    $result = $handler->__invoke(new UpdateSessionTokensCommand(
      currentRefreshTokenId: 'refresh-missing',
      currentAccessTokenId: 'access-2',
      newAccessTokenId: 'access-new',
      newRefreshTokenId: 'refresh-new',
    ));

    self::assertTrue($result->updated);
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

    $handler = new UpdateSessionTokensHandler($repository);

    $result = $handler->__invoke(new UpdateSessionTokensCommand(
      currentRefreshTokenId: 'refresh-missing',
      currentAccessTokenId: 'access-missing',
      newAccessTokenId: 'access-new',
      newRefreshTokenId: 'refresh-new',
    ));

    self::assertFalse($result->updated);
  }
  // #endregion
}
