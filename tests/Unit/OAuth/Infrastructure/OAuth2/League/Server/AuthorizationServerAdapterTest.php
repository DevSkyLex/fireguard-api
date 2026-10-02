<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\OAuth2\League\Server;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response;
use OAuth\Application\Contract\Token\{AccessTokenGrantParameters, AccessTokenRequest};
use OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenResult;
use OAuth\Domain\Exception\Token\AuthorizationException;
use OAuth\Infrastructure\OAuth2\League\Server\AuthorizationServerAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function base64_encode;
use function func_num_args;
use function json_encode;
use function rtrim;
use function str_repeat;
use function strtr;

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
        'access_token' => $this->issuedJwt(),
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'refresh_token' => 'refresh-token',
        'scope' => 'openid profile',
      ])));

    $adapter = new AuthorizationServerAdapter($authorizationServer, $this->grantLifecycle());

    $result = $adapter->issueAccessToken(new AccessTokenRequest(
      grantType: 'client_credentials',
      clientId: 'client-id',
      clientSecret: 'client-secret',
      grant: new AccessTokenGrantParameters(
        scope: 'openid profile',
      ),
    ));

    self::assertInstanceOf(IssueTokenResult::class, $result);
    self::assertSame($this->issuedJwt(), $result->accessToken);
    self::assertSame(str_repeat('a', 80), $result->tokenId);
    self::assertSame('Bearer', $result->tokenType);
    self::assertSame(3600, $result->expiresIn);
    self::assertSame('refresh-token', $result->refreshToken);
    self::assertSame('OPENID PROFILE', $result->scope);
  }

  /**
   * @param mixed $identifier an invalid issued identifier
   */
  #[Test]
  #[DataProvider('unusableIdentifiers')]
  public function testIssueAccessTokenRejectsUnusableIdentifiersWithoutBearerFallback(mixed $identifier): void
  {
    $accessToken = $this->issuedJwt($identifier);
    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())->method('respondToAccessTokenRequest')->willReturn(new Response(200, [], (string) json_encode(['access_token' => $accessToken])));

    try {
      new AuthorizationServerAdapter($authorizationServer, $this->grantLifecycle())->issueAccessToken(new AccessTokenRequest(grantType: 'client_credentials', clientId: 'client-id', clientSecret: 'client-secret'));
      self::fail('Expected an unusable identifier to reject issuance.');
    } catch (AuthorizationException $exception) {
      self::assertSame('server_error', $exception->errorType());
      self::assertStringNotContainsString($accessToken, $exception->getMessage());
    }
  }

  /**
   * @return array<string, array{mixed}>
   */
  public static function unusableIdentifiers(): array
  {
    return ['missing' => [null], 'empty' => [''], 'non-string' => [42], 'too long' => [str_repeat('a', 101)]];
  }

  #[Test]
  public function testIssueAccessTokenForwardsAuthorizationCodeAndPkceParameters(): void
  {
    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('respondToAccessTokenRequest')
      ->with(self::callback(static fn (ServerRequestInterface $request): bool => [
        'grant_type' => 'authorization_code',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'scope' => 'openid',
        'code' => 'auth-code',
        'redirect_uri' => 'https://client.example/callback',
        'code_verifier' => 'pkce-verifier',
      ] === $request->getParsedBody()))
      ->willReturn(new Response(200, [], (string) json_encode(['access_token' => $this->issuedJwt()])));

    $adapter = new AuthorizationServerAdapter($authorizationServer, $this->grantLifecycle());
    $adapter->issueAccessToken(new AccessTokenRequest(
      grantType: 'authorization_code',
      clientId: 'client-id',
      clientSecret: 'client-secret',
      grant: new AccessTokenGrantParameters(
        scope: 'openid',
        code: 'auth-code',
        redirectUri: 'https://client.example/callback',
        codeVerifier: 'pkce-verifier',
      ),
    ));
  }

  #[Test]
  #[DataProvider('oauthErrorProvider')]
  public function testIssueAccessTokenMapsOAuthServerException(string $errorType, string $expectedErrorType): void
  {
    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('respondToAccessTokenRequest')
      ->willThrowException(new OAuthServerException('boom', 0, $errorType, 400));

    $adapter = new AuthorizationServerAdapter($authorizationServer, $this->grantLifecycle());

    try {
      $adapter->issueAccessToken(new AccessTokenRequest(
        grantType: 'client_credentials',
        clientId: 'client-id',
        clientSecret: 'client-secret',
      ));
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

    $adapter = new AuthorizationServerAdapter($authorizationServer, $this->grantLifecycle());

    try {
      $adapter->issueAccessToken(new AccessTokenRequest(
        grantType: 'authorization_code',
        clientId: 'client-id',
        clientSecret: 'client-secret',
        grant: new AccessTokenGrantParameters(
          code: 'auth-code',
        ),
      ));
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

    $adapter = new AuthorizationServerAdapter($authorizationServer, $this->grantLifecycle());

    try {
      $adapter->issueAccessToken(new AccessTokenRequest(
        grantType: 'refresh_token',
        clientId: 'client-id',
        clientSecret: 'client-secret',
        grant: new AccessTokenGrantParameters(
          refreshToken: 'refresh-token',
        ),
      ));
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

    $adapter = new AuthorizationServerAdapter($authorizationServer, $this->grantLifecycle());

    try {
      $adapter->issueAccessToken(new AccessTokenRequest(
        grantType: 'refresh_token',
        clientId: 'client-id',
        clientSecret: 'client-secret',
        grant: new AccessTokenGrantParameters(
          refreshToken: 'refresh-token',
        ),
      ));
      self::fail('Expected AuthorizationException to be thrown.');
    } catch (AuthorizationException $exception) {
      self::assertSame('invalid_grant', $exception->errorType());
      self::assertSame('Invalid refresh token.', $exception->getMessage());
    }
  }

  #[Test]
  public function testIssueAccessTokenMapsThrowableToInvalidGrantForAuthorizationCode(): void
  {
    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('respondToAccessTokenRequest')
      ->willThrowException(new RuntimeException('boom'));

    $adapter = new AuthorizationServerAdapter($authorizationServer, $this->grantLifecycle());

    try {
      $adapter->issueAccessToken(new AccessTokenRequest(
        grantType: 'authorization_code',
        clientId: 'client-id',
        clientSecret: 'client-secret',
        grant: new AccessTokenGrantParameters(
          code: 'auth-code',
        ),
      ));
      self::fail('Expected AuthorizationException to be thrown.');
    } catch (AuthorizationException $exception) {
      self::assertSame('invalid_grant', $exception->errorType());
      self::assertSame('Invalid authorization code.', $exception->getMessage());
    }
  }

  #[Test]
  public function testIssueAccessTokenMapsThrowableToServerErrorForOtherGrant(): void
  {
    $authorizationServer = $this->createMock(AuthorizationServer::class);
    $authorizationServer->expects(self::once())
      ->method('respondToAccessTokenRequest')
      ->willThrowException(new RuntimeException('boom'));

    $adapter = new AuthorizationServerAdapter($authorizationServer, $this->grantLifecycle());

    try {
      $adapter->issueAccessToken(new AccessTokenRequest(
        grantType: 'client_credentials',
        clientId: 'client-id',
        clientSecret: 'client-secret',
      ));
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
      'custom_error' => ['custom_error', 'server_error'],
    ];
  }

  // #endregion
  /**
   * @return \OAuth\Application\Port\Outbound\Token\GrantLifecyclePort the transaction boundary
   */
  private function grantLifecycle(): \OAuth\Application\Port\Outbound\Token\GrantLifecyclePort
  {
    $lifecycle = $this->createStub(\OAuth\Application\Port\Outbound\Token\GrantLifecyclePort::class);
    $lifecycle->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());

    return $lifecycle;
  }

  private function issuedJwt(mixed $identifier = null): string
  {
    return rtrim(strtr(base64_encode('{"alg":"RS256"}'), '+/', '-_'), '=') . '.'
      . rtrim(strtr(base64_encode((string) json_encode(0 === func_num_args() ? ['jti' => str_repeat('a', 80), 'scopes' => ['OPENID', 'PROFILE']] : ['jti' => $identifier])), '+/', '-_'), '=') . '.c2lnbmF0dXJl';
  }
}
