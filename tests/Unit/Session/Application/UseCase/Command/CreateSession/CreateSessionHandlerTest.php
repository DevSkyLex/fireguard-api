<?php

declare(strict_types=1);

namespace Tests\Session\Application\UseCase\Command\CreateSession;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Command\CreateSession\CreateSessionCommand;
use Session\Application\UseCase\Command\CreateSession\CreateSessionHandler;
use Session\Application\UseCase\Command\CreateSession\CreateSessionResult;
use Session\Domain\Model\Session;
use Session\Domain\ValueObject\SessionId;
use Shared\Application\Factory\UuidFactory;
use Shared\Domain\ValueObject\IpAddress;
use Shared\Domain\ValueObject\UserAgent;

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
      ipAddress: new IpAddress(value: '192.168.1.1'),
      userAgent: new UserAgent(value: 'Mozilla/5.0'),
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
