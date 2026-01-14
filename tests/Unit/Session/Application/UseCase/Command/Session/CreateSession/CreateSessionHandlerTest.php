<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Application\UseCase\Command\Session\CreateSession;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Command\Session\CreateSession\{CreateSessionCommand, CreateSessionHandler, CreateSessionResult};
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\SessionId;
use Shared\Application\Factory\UuidFactory;

/**
 * Test CreateSessionHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: CreateSessionHandler::class)]
final class CreateSessionHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeCreatesNewSession.
   *
   * Test that __invoke creates a new session successfully.
   */
  #[Test]
  public function testInvokeCreatesNewSession(): void
  {
    $sessionId = '123e4567-e89b-12d3-a456-426614174000';

    // Mocks
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(SessionId::class)
      ->willReturn(new SessionId($sessionId));

    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(Session::class));

    // Command
    $command = new CreateSessionCommand(
      userId: 'user-123',
      ipAddress: '192.168.1.1',
      userAgent: 'Mozilla/5.0',
      accessTokenId: 'access-token-id',
      refreshTokenId: 'refresh-token-id',
      rememberMe: true,
    );

    // Handler
    $handler = new CreateSessionHandler(
      sessionRepository: $repository,
      uuidFactory: $uuidFactory,
    );

    // Execute
    $result = $handler->__invoke(command: $command);

    // Assert
    self::assertInstanceOf(CreateSessionResult::class, $result);
    self::assertEquals($sessionId, $result->sessionId);
  }
  // #endregion
}
