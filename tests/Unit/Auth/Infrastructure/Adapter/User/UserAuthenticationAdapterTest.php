<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\User;

use Auth\Infrastructure\Adapter\User\UserAuthenticationAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\{LoggerInterface, NullLogger};
use RuntimeException;
use Throwable;
use User\Application\Port\Inbound\UserAuthenticationPort;

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
    $queryBus = $this->createMock(UserAuthenticationPort::class);
    $queryBus->expects(self::once())
      ->method('authenticate')
      ->with('user@example.com', 'password')
      ->willReturn(new \User\Application\Contract\User\UserAuthentication(
        authenticated: true,
        userId: 'user-1',
        email: 'user@example.com',
      ));

    $adapter = new UserAuthenticationAdapter($queryBus, new NullLogger());

    $result = $adapter->authenticate('user@example.com', 'password');

    self::assertTrue($result->authenticated);
    self::assertSame('user-1', $result->userId);
    self::assertSame('user@example.com', $result->email);
  }

  #[Test]
  public function testAuthenticateReturnsFailedWhenNotAuthenticated(): void
  {
    $queryBus = $this->createMock(UserAuthenticationPort::class);
    $queryBus->expects(self::once())
      ->method('authenticate')
      ->willReturn(new \User\Application\Contract\User\UserAuthentication(authenticated: false));

    $adapter = new UserAuthenticationAdapter($queryBus, new NullLogger());

    $result = $adapter->authenticate('user@example.com', 'password');

    self::assertFalse($result->authenticated);
  }

  #[Test]
  public function testAuthenticateReturnsFailedOnException(): void
  {
    $queryBus = $this->createMock(UserAuthenticationPort::class);
    $exception = new class ('fail') extends RuntimeException implements Throwable {
    };
    $queryBus->expects(self::once())
      ->method('authenticate')
      ->willThrowException($exception);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('critical')
      ->with('Password credential verification failed unexpectedly.', [
        'exception_class' => $exception::class,
      ]);

    $adapter = new UserAuthenticationAdapter($queryBus, $logger);

    $result = $adapter->authenticate('user@example.com', 'password');

    self::assertFalse($result->authenticated);
  }
  // #endregion
}
