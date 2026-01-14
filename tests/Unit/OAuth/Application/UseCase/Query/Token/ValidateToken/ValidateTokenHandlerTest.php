<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Query\Token\ValidateToken;

use OAuth\Application\Port\Outbound\Token\{AccessTokenRepositoryPort, JwtParserPort};
use OAuth\Application\UseCase\Query\Token\ValidateToken\{ValidateTokenHandler, ValidateTokenQuery};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ValidateTokenHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ValidateTokenHandler::class)]
final class ValidateTokenHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeReturnsInvalidWhenValidationFails.
   *
   * @return void no return value
   */
  #[Test]
  public function testInvokeReturnsInvalidWhenValidationFails(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->with('access-token')
      ->willReturn(false);
    $jwtParser->expects(self::never())
      ->method('parse');

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::never())->method('find');

    $handler = new ValidateTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $accessTokenRepository,
    );

    $result = $handler->__invoke(new ValidateTokenQuery(accessToken: 'access-token'));

    self::assertFalse($result->valid);
    self::assertSame('Token signature or claims are invalid', $result->errorMessage);
  }
  // #endregion
}
