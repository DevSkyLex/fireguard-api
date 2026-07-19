<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Application\UseCase\Command\Session\RevokeOtherUserSessions;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Command\Session\RevokeOtherUserSessions\{RevokeOtherUserSessionsCommand, RevokeOtherUserSessionsHandler, RevokeOtherUserSessionsResult};

/**
 * Test RevokeOtherUserSessionsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RevokeOtherUserSessionsHandler::class)]
final class RevokeOtherUserSessionsHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeRevokesAllSessionsExceptCurrent(): void
  {
    /** @var SessionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('revokeAllForUserExcept')
      ->with('user-1', 'session-current')
      ->willReturn(2);

    $handler = new RevokeOtherUserSessionsHandler($repository);

    $result = $handler->__invoke(new RevokeOtherUserSessionsCommand(
      userId: 'user-1',
      currentSessionId: 'session-current',
      reason: 'logout others',
    ));

    self::assertInstanceOf(RevokeOtherUserSessionsResult::class, $result);
    self::assertSame(2, $result->revokedCount);
  }

  #[Test]
  public function testInvokeIsIdempotentWhenNothingLeftToRevoke(): void
  {
    /** @var SessionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('revokeAllForUserExcept')
      ->with('user-1', 'session-current')
      ->willReturn(0);

    $handler = new RevokeOtherUserSessionsHandler($repository);

    $result = $handler->__invoke(new RevokeOtherUserSessionsCommand(
      userId: 'user-1',
      currentSessionId: 'session-current',
    ));

    self::assertSame(0, $result->revokedCount);
  }
  // #endregion
}
