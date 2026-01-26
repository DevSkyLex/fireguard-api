<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\User;

use Auth\Infrastructure\Adapter\User\UserAuthenticationAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Throwable;
use User\Application\UseCase\Query\User\AuthenticateUser\{AuthenticateUserQuery, AuthenticateUserResult};

/**
 * Test UserAuthenticationAdapterTest.
 *
 * @category Adapter Tests
 */
#[CoversClass(className: UserAuthenticationAdapter::class)]
final class UserAuthenticationAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testAuthenticateReturnsSuccess(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(AuthenticateUserQuery::class))
      ->willReturn(new AuthenticateUserResult(
        authenticated: true,
        userId: 'user-1',
        email: 'user@example.com',
      ));

    $adapter = new UserAuthenticationAdapter($queryBus);

    $result = $adapter->authenticate('user@example.com', 'password');

    self::assertTrue($result->authenticated);
    self::assertSame('user-1', $result->userId);
    self::assertSame('user@example.com', $result->email);
  }

  #[Test]
  public function testAuthenticateReturnsFailedWhenNotAuthenticated(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new AuthenticateUserResult(authenticated: false));

    $adapter = new UserAuthenticationAdapter($queryBus);

    $result = $adapter->authenticate('user@example.com', 'password');

    self::assertFalse($result->authenticated);
  }

  #[Test]
  public function testAuthenticateReturnsFailedOnException(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(new class ('fail') extends RuntimeException implements Throwable {
      });

    $adapter = new UserAuthenticationAdapter($queryBus);

    $result = $adapter->authenticate('user@example.com', 'password');

    self::assertFalse($result->authenticated);
  }
  // #endregion
}
