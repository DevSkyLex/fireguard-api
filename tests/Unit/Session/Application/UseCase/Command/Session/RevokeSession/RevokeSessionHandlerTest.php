<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Application\UseCase\Command\Session\RevokeSession;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Command\Session\RevokeSession\{RevokeSessionCommand, RevokeSessionHandler};
use Session\Domain\Exception\SessionNotFoundException;
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\SessionId;
use Shared\Domain\ValueObject\{IpAddress, UserAgent};

/**
 * Test RevokeSessionHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RevokeSessionHandler::class)]
final class RevokeSessionHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeRevokesExistingSession.
   *
   * Test that __invoke revokes an existing session successfully.
   */
  #[Test]
  public function testInvokeRevokesExistingSession(): void
  {
    $sessionId = '123e4567-e89b-12d3-a456-426614174000';

    $session = Session::create(
      id: new SessionId($sessionId),
      userId: 'user-123',
      ipAddress: new IpAddress('192.168.1.1'),
      userAgent: new UserAgent('Mozilla/5.0'),
      metadata: null,
      accessTokenId: null,
      refreshTokenId: null,
    );

    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($session);
    $repository->expects(self::once())
      ->method('save');

    $command = new RevokeSessionCommand(sessionId: $sessionId);

    $handler = new RevokeSessionHandler(sessionRepository: $repository);
    $handler->__invoke(command: $command);

    self::assertTrue($session->isRevoked());
  }

  /**
   * Method testInvokeThrowsExceptionWhenSessionNotFound.
   *
   * Test that __invoke throws exception when session not found.
   */
  #[Test]
  public function testInvokeThrowsExceptionWhenSessionNotFound(): void
  {
    $sessionId = '123e4567-e89b-12d3-a456-426614174000';

    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $command = new RevokeSessionCommand(sessionId: $sessionId);

    $handler = new RevokeSessionHandler(sessionRepository: $repository);

    $this->expectException(SessionNotFoundException::class);
    $handler->__invoke(command: $command);
  }
  // #endregion
}
