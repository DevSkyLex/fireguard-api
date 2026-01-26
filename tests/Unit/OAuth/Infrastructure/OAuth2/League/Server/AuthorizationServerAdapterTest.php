<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\OAuth2\League\Server;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response;
use OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenResult;
use OAuth\Domain\Exception\Token\AuthorizationException;
use OAuth\Infrastructure\OAuth2\League\Server\AuthorizationServerAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function json_encode;

/**
 * Test AuthorizationServerAdapterTest.
 *
 * @category Server Adapter Tests
 */
#[CoversClass(className: AuthorizationServerAdapter::class)]
final class AuthorizationServerAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testIssueAccessTokenReturnsResult(): void
  {
    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('respondToAccessTokenRequest')
      ->willReturn(new Response(200, [], (string) json_encode([
        'access_token' => 'access-token',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'refresh_token' => 'refresh-token',
        'scope' => 'openid profile',
      ])));

    $adapter = new AuthorizationServerAdapter($authorizationServer);

    $result = $adapter->issueAccessToken(
      grantType: 'client_credentials',
      clientId: 'client-id',
      clientSecret: 'client-secret',
      scope: 'openid profile',
    );

    self::assertInstanceOf(IssueTokenResult::class, $result);
    self::assertSame('access-token', $result->accessToken);
    self::assertSame('Bearer', $result->tokenType);
    self::assertSame(3600, $result->expiresIn);
    self::assertSame('refresh-token', $result->refreshToken);
    self::assertSame('openid profile', $result->scope);
  }

  #[Test]
  #[DataProvider('oauthErrorProvider')]
  public function testIssueAccessTokenMapsOAuthServerException(string $errorType, string $expectedErrorType): void
  {
    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('respondToAccessTokenRequest')
      ->willThrowException(new OAuthServerException('boom', 0, $errorType, 400));

    $adapter = new AuthorizationServerAdapter($authorizationServer);

    try {
      $adapter->issueAccessToken(
        grantType: 'client_credentials',
        clientId: 'client-id',
        clientSecret: 'client-secret',
      );
      self::fail('Expected AuthorizationException to be thrown.');
    } catch (AuthorizationException $exception) {
      self::assertSame($expectedErrorType, $exception->errorType());
    }
  }

  #[Test]
  public function testIssueAccessTokenMapsServerErrorForAuthorizationCodeGrant(): void
  {
    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('respondToAccessTokenRequest')
      ->willThrowException(new OAuthServerException('server error', 0, 'server_error', 500));

    $adapter = new AuthorizationServerAdapter($authorizationServer);

    try {
      $adapter->issueAccessToken(
        grantType: 'authorization_code',
        clientId: 'client-id',
        clientSecret: 'client-secret',
        code: 'auth-code',
      );
      self::fail('Expected AuthorizationException to be thrown.');
    } catch (AuthorizationException $exception) {
      self::assertSame('invalid_grant', $exception->errorType());
      self::assertSame('Invalid authorization code.', $exception->getMessage());
    }
  }

  #[Test]
  public function testIssueAccessTokenMapsServerErrorForRefreshTokenGrant(): void
  {
    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('respondToAccessTokenRequest')
      ->willThrowException(new OAuthServerException('server error', 0, 'server_error', 500));

    $adapter = new AuthorizationServerAdapter($authorizationServer);

    try {
      $adapter->issueAccessToken(
        grantType: 'refresh_token',
        clientId: 'client-id',
        clientSecret: 'client-secret',
        refreshToken: 'refresh-token',
      );
      self::fail('Expected AuthorizationException to be thrown.');
    } catch (AuthorizationException $exception) {
      self::assertSame('invalid_grant', $exception->errorType());
      self::assertSame('Invalid refresh token.', $exception->getMessage());
    }
  }

  #[Test]
  public function testIssueAccessTokenMapsThrowableToInvalidGrantForRefreshToken(): void
  {
    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('respondToAccessTokenRequest')
      ->willThrowException(new RuntimeException('boom'));

    $adapter = new AuthorizationServerAdapter($authorizationServer);

    try {
      $adapter->issueAccessToken(
        grantType: 'refresh_token',
        clientId: 'client-id',
        clientSecret: 'client-secret',
        refreshToken: 'refresh-token',
      );
      self::fail('Expected AuthorizationException to be thrown.');
    } catch (AuthorizationException $exception) {
      self::assertSame('invalid_grant', $exception->errorType());
      self::assertSame('Invalid refresh token.', $exception->getMessage());
    }
  }

  #[Test]
  public function testIssueAccessTokenMapsThrowableToServerErrorForOtherGrant(): void
  {
    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('respondToAccessTokenRequest')
      ->willThrowException(new RuntimeException('boom'));

    $adapter = new AuthorizationServerAdapter($authorizationServer);

    try {
      $adapter->issueAccessToken(
        grantType: 'client_credentials',
        clientId: 'client-id',
        clientSecret: 'client-secret',
      );
      self::fail('Expected AuthorizationException to be thrown.');
    } catch (AuthorizationException $exception) {
      self::assertSame('server_error', $exception->errorType());
      self::assertSame('Authorization server error.', $exception->getMessage());
    }
  }
  // #endregion

  // #region Providers
  /**
   * @return array<string, array{string, string}>
   */
  public static function oauthErrorProvider(): array
  {
    return [
      'invalid_request' => ['invalid_request', 'invalid_request'],
      'invalid_client' => ['invalid_client', 'invalid_client'],
      'invalid_grant' => ['invalid_grant', 'invalid_grant'],
      'invalid_scope' => ['invalid_scope', 'invalid_scope'],
      'unauthorized_client' => ['unauthorized_client', 'unauthorized_client'],
      'unsupported_grant_type' => ['unsupported_grant_type', 'unsupported_grant_type'],
      'access_denied' => ['access_denied', 'access_denied'],
      'temporarily_unavailable' => ['temporarily_unavailable', 'temporarily_unavailable'],
      'server_error' => ['server_error', 'server_error'],
    ];
  }
  // #endregion
}
