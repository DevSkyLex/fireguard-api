<?php

declare(strict_types=1);

namespace Tests\Session\Presentation\Api\Processor;

use ApiPlatform\Metadata\Delete;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Command\RevokeSession\RevokeSessionHandler;
use Session\Domain\Model\Session;
use Session\Domain\ValueObject\SessionId;
use Session\Presentation\Api\Processor\RevokeSessionProcessor;
use Shared\Domain\ValueObject\IpAddress;
use Shared\Domain\ValueObject\UserAgent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test RevokeSessionProcessorTest
 * @final
 *
 * Test class for RevokeSessionProcessor.
 *
 * @category Processor Tests
 * @package Tests\Session\Presentation\Api\Processor
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RevokeSessionProcessor::class)]
final class RevokeSessionProcessorTest extends TestCase
{
  //#region Methods

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

  /**
   * Method testProcessRevokesSession
   *
   * Test that process revokes a session.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testProcessRevokesSession(): void
  {
    $sessionId = '123e4567-e89b-12d3-a456-426614174000';

    $session = Session::create(
      id: new SessionId($sessionId),
      userId: 'user-123',
      ipAddress: new IpAddress('192.168.1.1'),
      userAgent: new UserAgent('Mozilla/5.0'),
    );

    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($session);
    $repository->expects(self::once())
      ->method('save');

    $handler = new RevokeSessionHandler(sessionRepository: $repository);
    $processor = new RevokeSessionProcessor(
      handler: $handler,
      security: $this->createSecurityMock(),
    );

    $processor->process(
      data: new \stdClass(),
      operation: new Delete(),
      uriVariables: ['id' => $sessionId],
      context: [],
    );

    // No exception means success
    self::assertTrue($session->isRevoked());
  }

  /**
   * Method testProcessThrowsNotFoundWhenSessionMissing
   *
   * Test that process throws NotFoundHttpException when session is missing.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testProcessThrowsNotFoundWhenSessionMissing(): void
  {
    $sessionId = '123e4567-e89b-12d3-a456-426614174000';

    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new RevokeSessionHandler(sessionRepository: $repository);
    $processor = new RevokeSessionProcessor(
      handler: $handler,
      security: $this->createSecurityMock(),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: new \stdClass(),
      operation: new Delete(),
      uriVariables: ['id' => $sessionId],
      context: [],
    );
  }

  /**
   * Method testProcessThrowsNotFoundWhenIdMissing
   *
   * Test that process throws NotFoundHttpException when ID is missing.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testProcessThrowsNotFoundWhenIdMissing(): void
  {
    $repository = $this->createMock(SessionRepositoryPort::class);
    $handler = new RevokeSessionHandler(sessionRepository: $repository);
    $processor = new RevokeSessionProcessor(
      handler: $handler,
      security: $this->createSecurityMock(),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: new \stdClass(),
      operation: new Delete(),
      uriVariables: [],
      context: [],
    );
  }
  //#endregion
}
