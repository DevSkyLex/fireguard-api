<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Presentation\Api\Processor\Session;

use ApiPlatform\Metadata\Delete;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Session\Application\UseCase\Command\Session\RevokeSession\RevokeSessionCommand;
use Session\Domain\Exception\SessionNotFoundException;
use Session\Presentation\Api\Processor\Session\RevokeSessionProcessor;
use Shared\Application\Port\Inbound\CommandBusPort;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test RevokeSessionProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RevokeSessionProcessor::class)]
final class RevokeSessionProcessorTest extends TestCase
{
  /**
   * Method testProcessRevokesSession.
   *
   * Test that process revokes a session.
   */
  #[Test]
  public function testProcessRevokesSession(): void
  {
    $sessionId = '123e4567-e89b-12d3-a456-426614174000';

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        fn (RevokeSessionCommand $command): bool => $command->sessionId === $sessionId,
      ));

    $processor = new RevokeSessionProcessor(
      commandBus: $commandBus,
      security: $this->createSecurityMock(),
    );

    $processor->process(
      data: new stdClass(),
      operation: new Delete(),
      uriVariables: ['id' => $sessionId],
      context: [],
    );
  }

  /**
   * Method testProcessThrowsNotFoundWhenSessionMissing.
   *
   * Test that process throws NotFoundHttpException when session is missing.
   */
  #[Test]
  public function testProcessThrowsNotFoundWhenSessionMissing(): void
  {
    $sessionId = '123e4567-e89b-12d3-a456-426614174000';

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(SessionNotFoundException::withId(id: $sessionId));

    $processor = new RevokeSessionProcessor(
      commandBus: $commandBus,
      security: $this->createSecurityMock(),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: new stdClass(),
      operation: new Delete(),
      uriVariables: ['id' => $sessionId],
      context: [],
    );
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenNestedSessionMissing(): void
  {
    $sessionId = '123e4567-e89b-12d3-a456-426614174001';
    $nested = SessionNotFoundException::withId(id: $sessionId);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('boom', 0, $nested));

    $processor = new RevokeSessionProcessor(
      commandBus: $commandBus,
      security: $this->createSecurityMock(),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: new stdClass(),
      operation: new Delete(),
      uriVariables: ['id' => $sessionId],
      context: [],
    );
  }

  /**
   * Method testProcessThrowsNotFoundWhenIdMissing.
   *
   * Test that process throws NotFoundHttpException when ID is missing.
   */
  #[Test]
  public function testProcessThrowsNotFoundWhenIdMissing(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $processor = new RevokeSessionProcessor(
      commandBus: $commandBus,
      security: $this->createSecurityMock(),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: new stdClass(),
      operation: new Delete(),
      uriVariables: [],
      context: [],
    );
  }

  #[Test]
  public function testProcessThrowsWhenUserMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new RevokeSessionProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: new stdClass(),
      operation: new Delete(),
      uriVariables: ['id' => 'session-1'],
      context: [],
    );
  }

  #[Test]
  public function testProcessRethrowsUnexpectedException(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('boom'));

    $processor = new RevokeSessionProcessor(
      commandBus: $commandBus,
      security: $this->createSecurityMock(),
    );

    $this->expectException(RuntimeException::class);

    $processor->process(
      data: new stdClass(),
      operation: new Delete(),
      uriVariables: ['id' => 'session-1'],
      context: [],
    );
  }
  // #region Methods

  /**
   * Creates a Security mock with an authenticated user.
   */
  private function createSecurityMock(): Security
  {
    $user = $this->createMock(UserInterface::class);
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($user);

    return $security;
  }
  // #endregion
}
