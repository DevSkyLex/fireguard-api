<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Application\UseCase\Command\Session\RevokeAllUserSessions;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Command\Session\RevokeAllUserSessions\{RevokeAllUserSessionsCommand, RevokeAllUserSessionsHandler, RevokeAllUserSessionsResult};

/**
 * Test RevokeAllUserSessionsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RevokeAllUserSessionsHandler::class)]
final class RevokeAllUserSessionsHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeRevokesAllSessions(): void
  {
    /** @var SessionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('revokeAllForUser')
      ->with('user-1')
      ->willReturn(2);

    $handler = new RevokeAllUserSessionsHandler($repository);

    $result = $handler->__invoke(new RevokeAllUserSessionsCommand(
      userId: 'user-1',
      reason: 'logout',
    ));

    self::assertInstanceOf(RevokeAllUserSessionsResult::class, $result);
    self::assertSame(2, $result->revokedCount);
  }
  // #endregion
}
