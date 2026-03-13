<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Application\UseCase\Query\Session\ListUserSessions;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Query\Session\ListUserSessions\{ListUserSessionsHandler, ListUserSessionsQuery};
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\SessionId;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Domain\ValueObject\{IpAddress, UserAgent};

/**
 * Test ListUserSessionsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListUserSessionsHandler::class)]
final class ListUserSessionsHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeReturnsAllSessions(): void
  {
    $session = Session::create(
      id: new SessionId('550e8400-e29b-41d4-a716-446655440020'),
      userId: 'user-1',
      ipAddress: new IpAddress('127.0.0.1'),
      userAgent: new UserAgent('Mozilla/5.0'),
    );

    /** @var SessionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->with('user-1')
      ->willReturn([$session]);

    $handler = new ListUserSessionsHandler($repository);

    $result = $handler->__invoke(new ListUserSessionsQuery(
      userId: 'user-1',
      activeOnly: false,
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertSame(1, $result->total);
    self::assertSame(1, $result->limit);
    self::assertSame(0, $result->offset);
  }

  #[Test]
  public function testInvokeReturnsActiveSessions(): void
  {
    $session = Session::create(
      id: new SessionId('550e8400-e29b-41d4-a716-446655440021'),
      userId: 'user-2',
      ipAddress: new IpAddress('127.0.0.1'),
      userAgent: new UserAgent('Mozilla/5.0'),
    );

    /** @var SessionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(SessionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findActiveByUserId')
      ->with('user-2')
      ->willReturn([$session]);

    $handler = new ListUserSessionsHandler($repository);

    $result = $handler->__invoke(new ListUserSessionsQuery(
      userId: 'user-2',
      activeOnly: true,
    ));

    self::assertCount(1, $result->items);
    self::assertSame(1, $result->total);
    self::assertSame(1, $result->limit);
    self::assertSame(0, $result->offset);
  }
  // #endregion
}
