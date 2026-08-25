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
   * Method testProcessLetsAMissingSessionPropagate.
   *
   * The processor no longer maps: `exception_to_status` does, once
   * `BusFailureUnwrappingSubscriber` has opened the envelope. Its job here is
   * to get out of the way, which is what this asserts.
   *
   * The companion test that fed a NESTED exception is gone with the mapping —
   * unwrapping is now one behaviour in one place, covered by
   * `BusFailureUnwrappingSubscriberTest`, instead of being re-proven at every
   * processor that used to re-implement it.
   */
  #[Test]
  public function testProcessLetsAMissingSessionPropagate(): void
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

    $this->expectException(SessionNotFoundException::class);

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
    $commandBus = $this->createStub(CommandBusPort::class);
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
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new RevokeSessionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
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
    $user = $this->createStub(UserInterface::class);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    return $security;
  }
  // #endregion
}
