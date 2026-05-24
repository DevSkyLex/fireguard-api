<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Query\Token\ValidateToken;

use DateTimeImmutable;
use OAuth\Application\Port\Outbound\Token\{AccessTokenRepositoryPort, JwtParserPort};
use OAuth\Application\UseCase\Query\Token\ValidateToken\{ValidateTokenHandler, ValidateTokenQuery};
use OAuth\Domain\Model\Token\AccessToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

  #[Test]
  public function testInvokeReturnsInvalidWhenParseFails(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->willReturn(true);
    $jwtParser->expects(self::once())
      ->method('parse')
      ->willReturn(null);

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::never())->method('find');

    $handler = new ValidateTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $accessTokenRepository,
    );

    $result = $handler->__invoke(new ValidateTokenQuery(accessToken: 'access-token'));

    self::assertFalse($result->valid);
    self::assertSame('Failed to parse token', $result->errorMessage);
  }

  #[Test]
  public function testInvokeReturnsInvalidWhenTokenIdMissing(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->willReturn(true);
    $jwtParser->expects(self::once())
      ->method('parse')
      ->willReturn(['sub' => 'user-123']);

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::never())->method('find');

    $handler = new ValidateTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $accessTokenRepository,
    );

    $result = $handler->__invoke(new ValidateTokenQuery(accessToken: 'access-token'));

    self::assertFalse($result->valid);
    self::assertSame('Token has no identifier', $result->errorMessage);
  }

  #[Test]
  public function testInvokeReturnsInvalidWhenTokenNotFound(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->willReturn(true);
    $jwtParser->expects(self::once())
      ->method('parse')
      ->willReturn(['jti' => 'token-123']);

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::once())
      ->method('find')
      ->with('token-123')
      ->willReturn(null);

    $handler = new ValidateTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $accessTokenRepository,
    );

    $result = $handler->__invoke(new ValidateTokenQuery(accessToken: 'access-token'));

    self::assertFalse($result->valid);
    self::assertSame('Token not found', $result->errorMessage);
  }

  #[Test]
  public function testInvokeReturnsInvalidWhenTokenRevoked(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->willReturn(true);
    $jwtParser->expects(self::once())
      ->method('parse')
      ->willReturn(['jti' => 'token-123']);

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::once())
      ->method('find')
      ->with('token-123')
      ->willReturn($this->createAccessToken(revoked: true, expiry: new DateTimeImmutable('+1 hour')));

    $handler = new ValidateTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $accessTokenRepository,
    );

    $result = $handler->__invoke(new ValidateTokenQuery(accessToken: 'access-token'));

    self::assertFalse($result->valid);
    self::assertSame('Token has been revoked', $result->errorMessage);
  }

  #[Test]
  public function testInvokeReturnsInvalidWhenTokenExpired(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->willReturn(true);
    $jwtParser->expects(self::once())
      ->method('parse')
      ->willReturn(['jti' => 'token-123']);

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::once())
      ->method('find')
      ->with('token-123')
      ->willReturn($this->createAccessToken(revoked: false, expiry: new DateTimeImmutable('-1 hour')));

    $handler = new ValidateTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $accessTokenRepository,
    );

    $result = $handler->__invoke(new ValidateTokenQuery(accessToken: 'access-token'));

    self::assertFalse($result->valid);
    self::assertSame('Token has expired', $result->errorMessage);
  }

  #[Test]
  public function testInvokeReturnsValidWhenTokenActive(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->willReturn(true);
    $jwtParser->expects(self::once())
      ->method('parse')
      ->willReturn(['jti' => 'token-123']);

    $accessToken = $this->createAccessToken(revoked: false, expiry: new DateTimeImmutable('+1 hour'));

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::once())
      ->method('find')
      ->with('token-123')
      ->willReturn($accessToken);

    $handler = new ValidateTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $accessTokenRepository,
    );

    $result = $handler->__invoke(new ValidateTokenQuery(accessToken: 'access-token'));

    self::assertTrue($result->valid);
    self::assertSame('token-123', $result->tokenId);
    self::assertSame('user-123', $result->userId);
    self::assertSame(['OPENID'], $result->scopes);
  }

  #[Test]
  public function testInvokeReturnsInvalidWhenExceptionThrown(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->willThrowException(new RuntimeException('boom'));

    $handler = new ValidateTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $this->createStub(AccessTokenRepositoryPort::class),
    );

    $result = $handler->__invoke(new ValidateTokenQuery(accessToken: 'access-token'));

    self::assertFalse($result->valid);
    self::assertSame('boom', $result->errorMessage);
  }
  // #endregion

  // #region Helpers
  private function createAccessToken(bool $revoked, DateTimeImmutable $expiry): AccessToken
  {
    return new AccessToken(
      identifier: 'token-123',
      clientIdentifier: new OAuthClientIdentifier('client-123'),
      expiry: $expiry,
      scopes: Scopes::fromArray(['OPENID']),
      userIdentifier: 'user-123',
      isRevoked: $revoked,
    );
  }
  // #endregion
}
