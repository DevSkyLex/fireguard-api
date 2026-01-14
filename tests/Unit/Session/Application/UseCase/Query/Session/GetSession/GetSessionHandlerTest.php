<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Application\UseCase\Query\Session\GetSession;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Query\Session\GetSession\{GetSessionHandler, GetSessionQuery, GetSessionResult};
use Session\Domain\Exception\SessionNotFoundException;
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\{SessionId, SessionMetadata};
use Shared\Domain\ValueObject\{IpAddress, UserAgent};

/**
 * Test GetSessionHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: GetSessionHandler::class)]
final class GetSessionHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeReturnsSession.
   *
   * Test that __invoke returns session details successfully.
   */
  #[Test]
  public function testInvokeReturnsSession(): void
  {
    $sessionId = '123e4567-e89b-12d3-a456-426614174000';

    $session = Session::create(
      id: new SessionId($sessionId),
      userId: 'user-123',
      ipAddress: new IpAddress('192.168.1.1'),
      userAgent: new UserAgent('Mozilla/5.0'),
      metadata: new SessionMetadata(deviceType: 'desktop'),
      accessTokenId: null,
      refreshTokenId: null,
    );

    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($session);

    $query = new GetSessionQuery(sessionId: $sessionId);

    $handler = new GetSessionHandler(sessionRepository: $repository);
    $result = $handler->__invoke(query: $query);

    self::assertInstanceOf(GetSessionResult::class, $result);
    self::assertEquals($sessionId, $result->sessionId);
    self::assertEquals('user-123', $result->userId);
    self::assertEquals('192.168.1.1', $result->ipAddress);
    self::assertEquals('desktop', $result->deviceType);
    self::assertEquals('Mozilla/5.0', $result->userAgent);
    self::assertFalse($result->isRevoked);
  }

  /**
   * Method testInvokeThrowsExceptionWhenNotFound.
   *
   * Test that __invoke throws exception when session not found.
   */
  #[Test]
  public function testInvokeThrowsExceptionWhenNotFound(): void
  {
    $sessionId = '123e4567-e89b-12d3-a456-426614174000';

    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $query = new GetSessionQuery(sessionId: $sessionId);

    $handler = new GetSessionHandler(sessionRepository: $repository);

    $this->expectException(SessionNotFoundException::class);
    $handler->__invoke(query: $query);
  }
  // #endregion
}
