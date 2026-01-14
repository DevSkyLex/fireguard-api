<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Session\Logout;

use Auth\Application\Port\Outbound\TokenRevocationPort;
use Auth\Application\UseCase\Command\Session\Logout\{LogoutCommand, LogoutHandler, LogoutResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test LogoutHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: LogoutHandler::class)]
final class LogoutHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeRevokesProvidedTokens.
   */
  #[Test]
  public function testInvokeRevokesProvidedTokens(): void
  {
    /** @var TokenRevocationPort&MockObject $revocation */
    $revocation = $this->createMock(TokenRevocationPort::class);
    $revocation->expects(self::once())
      ->method('revokeRefreshToken')
      ->with('refresh-token')
      ->willReturn(true);
    $revocation->expects(self::once())
      ->method('revokeAccessToken')
      ->with('access-token')
      ->willReturn(true);

    $handler = new LogoutHandler(tokenRevocation: $revocation);
    $command = new LogoutCommand(refreshToken: 'refresh-token', accessToken: 'access-token');

    $result = $handler->__invoke(command: $command);

    $this->assertInstanceOf(LogoutResult::class, $result);
    $this->assertTrue($result->success);
    $this->assertTrue($result->refreshTokenRevoked);
    $this->assertTrue($result->accessTokenRevoked);
  }

  /**
   * Method testInvokeSkipsMissingTokens.
   */
  #[Test]
  public function testInvokeSkipsMissingTokens(): void
  {
    /** @var TokenRevocationPort&MockObject $revocation */
    $revocation = $this->createMock(TokenRevocationPort::class);
    $revocation->expects(self::never())->method('revokeRefreshToken');
    $revocation->expects(self::never())->method('revokeAccessToken');

    $handler = new LogoutHandler(tokenRevocation: $revocation);
    $command = new LogoutCommand(refreshToken: null, accessToken: '');

    $result = $handler->__invoke(command: $command);

    $this->assertTrue($result->success);
    $this->assertFalse($result->refreshTokenRevoked);
    $this->assertFalse($result->accessTokenRevoked);
  }
  // #endregion
}
