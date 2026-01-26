<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Command\Token\RevokeToken;

use OAuth\Application\Port\Outbound\Token\TokenRevocationPort;
use OAuth\Application\UseCase\Command\Token\RevokeToken\{RevokeTokenCommand, RevokeTokenHandler, RevokeTokenResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test RevokeTokenHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RevokeTokenHandler::class)]
final class RevokeTokenHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeRevokesRefreshTokenWithHint(): void
  {
    /** @var TokenRevocationPort&MockObject $revocation */
    $revocation = $this->createMock(TokenRevocationPort::class);
    $revocation->expects(self::once())
      ->method('revokeRefreshToken')
      ->with('token-1')
      ->willReturn(true);
    $revocation->expects(self::never())->method('revokeAccessToken');

    $handler = new RevokeTokenHandler($revocation);

    $result = $handler->__invoke(new RevokeTokenCommand(
      token: 'token-1',
      tokenTypeHint: RevokeTokenCommand::HINT_REFRESH_TOKEN,
    ));

    self::assertInstanceOf(RevokeTokenResult::class, $result);
    self::assertTrue($result->revoked);
  }

  #[Test]
  public function testInvokeRevokesAccessTokenWithHint(): void
  {
    /** @var TokenRevocationPort&MockObject $revocation */
    $revocation = $this->createMock(TokenRevocationPort::class);
    $revocation->expects(self::once())
      ->method('revokeAccessToken')
      ->with('token-2')
      ->willReturn(true);
    $revocation->expects(self::never())->method('revokeRefreshToken');

    $handler = new RevokeTokenHandler($revocation);

    $result = $handler->__invoke(new RevokeTokenCommand(
      token: 'token-2',
      tokenTypeHint: RevokeTokenCommand::HINT_ACCESS_TOKEN,
    ));

    self::assertTrue($result->revoked);
  }

  #[Test]
  public function testInvokeFallsBackWhenHintFails(): void
  {
    /** @var TokenRevocationPort&MockObject $revocation */
    $revocation = $this->createMock(TokenRevocationPort::class);
    $revocation->expects(self::once())
      ->method('revokeRefreshToken')
      ->with('token-3')
      ->willReturn(false);
    $revocation->expects(self::once())
      ->method('revokeAccessToken')
      ->with('token-3')
      ->willReturn(true);

    $handler = new RevokeTokenHandler($revocation);

    $result = $handler->__invoke(new RevokeTokenCommand(
      token: 'token-3',
      tokenTypeHint: null,
    ));

    self::assertTrue($result->revoked);
  }
  // #endregion
}
