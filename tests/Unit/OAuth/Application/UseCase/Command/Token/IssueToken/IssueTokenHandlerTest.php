<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Command\Token\IssueToken;

use DateTimeImmutable;
use OAuth\Application\Port\Outbound\Token\AccessTokenRepositoryPort;
use OAuth\Application\Port\Outbound\Token\AuthCodeRepositoryPort;
use OAuth\Application\Port\Outbound\Token\AuthorizationServerPort;
use OAuth\Application\Port\Outbound\Token\IdTokenIssuerPort;
use OAuth\Application\Port\Outbound\Token\RefreshTokenRepositoryPort;
use OAuth\Application\Port\Outbound\User\OidcUserProviderPort;
use OAuth\Application\Service\OidcClaimsBuilderInterface;
use OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenCommand;
use OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenHandler;
use OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenResult;
use OAuth\Domain\Event\Token\TokenIssuedEvent;
use OAuth\Domain\Event\Token\TokenIssueFailedEvent;
use OAuth\Domain\Exception\Token\AuthorizationException;
use OAuth\Domain\Model\Oidc\OidcUser;
use OAuth\Domain\Model\Token\AccessToken;
use OAuth\Domain\Model\Token\AuthCode;
use OAuth\Domain\Model\Token\RefreshToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scope;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Domain\ValueObject\Security\GrantType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Class IssueTokenHandlerTest.
 *
 * Unit tests for the IssueTokenHandler.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenHandler
 */
#[CoversClass(className: IssueTokenHandler::class)]
final class IssueTokenHandlerTest extends TestCase
{
  // #region Properties
  /**
   * Property authorizationServer.
   *
   * Mocked authorization server port.
   */
  private AuthorizationServerPort&MockObject $authorizationServer;

  /**
   * Property handler.
   *
   * IssueTokenHandler instance.
   */
  private IssueTokenHandler $handler;

  /**
   * Property eventDispatcher.
   *
   * Mocked event dispatcher.
   */
  private EventDispatcherPort&MockObject $eventDispatcher;

  /**
   * Property authCodeRepository.
   *
   * Mocked auth code repository.
   */
  private AuthCodeRepositoryPort&MockObject $authCodeRepository;

  /**
   * Property idTokenIssuer.
   *
   * Mocked ID token issuer.
   */
  private IdTokenIssuerPort&MockObject $idTokenIssuer;

  /**
   * Property oidcUserProvider.
   *
   * Mocked OIDC user provider.
   */
  private OidcUserProviderPort&MockObject $oidcUserProvider;

  /**
   * Property claimsBuilder.
   *
   * Mocked OIDC claims builder.
   */
  private OidcClaimsBuilderInterface&MockObject $claimsBuilder;

  /**
   * Property refreshTokenRepository.
   *
   * Mocked refresh token repository.
   */
  private RefreshTokenRepositoryPort&MockObject $refreshTokenRepository;

  /**
   * Property accessTokenRepository.
   *
   * Mocked access token repository.
   */
  private AccessTokenRepositoryPort&MockObject $accessTokenRepository;
  // #endregion

  // #region Methods
  /**
   * Method setUp.
   *
   * Sets up the test environment.
   *
   * @return void no return value
   */
  protected function setUp(): void
  {
    $this->authorizationServer = $this->createMock(AuthorizationServerPort::class);
    $this->eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $this->authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $this->idTokenIssuer = $this->createMock(IdTokenIssuerPort::class);
    $this->oidcUserProvider = $this->createMock(OidcUserProviderPort::class);
    $this->claimsBuilder = $this->createMock(OidcClaimsBuilderInterface::class);
    $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryPort::class);
    $this->accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);

    $this->handler = new IssueTokenHandler(
      $this->authorizationServer,
      $this->eventDispatcher,
      $this->authCodeRepository,
      $this->idTokenIssuer,
      $this->oidcUserProvider,
      $this->claimsBuilder,
      $this->refreshTokenRepository,
      $this->accessTokenRepository,
    );
  }

  /**
   * Method testHandleSuccessfullyIssuesToken.
   *
   * Tests that the handler successfully issues a token
   * when provided with valid credentials.
   *
   * @return void no return value
   */
  #[Test]
  public function testHandleSuccessfullyIssuesToken(): void
  {
    $command = new IssueTokenCommand(
      grantType: GrantType::CLIENT_CREDENTIALS->value,
      clientId: 'client_id',
      clientSecret: 'client_secret',
      scope: Scope::READ->value,
    );

    $expectedResult = new IssueTokenResult(
      accessToken: 'access_token_value',
      tokenType: 'Bearer',
      expiresIn: 3600,
    );

    $this->authorizationServer
      ->expects($this->once())
      ->method('issueAccessToken')
      ->with(
        $command->grantType,
        $command->clientId,
        $command->clientSecret,
        $command->scope,
        null,
        null,
        null,
        null,
      )
      ->willReturn($expectedResult);

    $this->eventDispatcher
      ->expects($this->once())
      ->method('dispatch')
      ->with($this->isInstanceOf(TokenIssuedEvent::class));

    $result = ($this->handler)($command);

    $this->assertInstanceOf(IssueTokenResult::class, $result);
    $this->assertEquals('access_token_value', $result->accessToken);
    $this->assertEquals('Bearer', $result->tokenType);
    $this->assertEquals(3600, $result->expiresIn);
  }

  /**
   * Method testHandleThrowsExceptionOnFailure.
   *
   * Tests that the handler throws an exception
   * when the authorization server fails.
   *
   * @return void no return value
   */
  #[Test]
  public function testHandleThrowsExceptionOnFailure(): void
  {
    $command = new IssueTokenCommand(
      grantType: 'invalid_grant',
      clientId: 'client_id',
      clientSecret: 'client_secret',
    );

    $this->authorizationServer
      ->expects($this->once())
      ->method('issueAccessToken')
      ->willThrowException(AuthorizationException::invalidGrant('Invalid grant type'));

    $this->eventDispatcher
      ->expects($this->once())
      ->method('dispatch')
      ->with($this->isInstanceOf(TokenIssueFailedEvent::class));

    $this->expectException(AuthorizationException::class);

    ($this->handler)($command);
  }

  /**
   * Method testHandleIssuesIdTokenForAuthorizationCode.
   *
   * Ensures the handler issues an ID token when
   * the authorization_code grant includes the openid scope.
   *
   * @return void no return value
   */
  #[Test]
  public function testHandleIssuesIdTokenForAuthorizationCode(): void
  {
    $command = new IssueTokenCommand(
      grantType: 'authorization_code',
      clientId: 'client-id',
      clientSecret: 'client-secret',
      scope: 'openid profile',
      code: 'auth-code',
      redirectUri: 'https://app.example.com/callback',
      codeVerifier: 'verifier',
    );

    $expectedResult = new IssueTokenResult(
      accessToken: 'access-token',
      tokenType: 'Bearer',
      expiresIn: 3600,
      refreshToken: 'refresh-token',
      scope: 'openid profile',
    );

    $authCode = new AuthCode(
      identifier: 'auth-code',
      expiryDateTime: new DateTimeImmutable('+10 minutes'),
      clientIdentifier: new OAuthClientIdentifier('client-id'),
      userIdentifier: 'user-id',
      scopes: Scopes::fromArray(['OPENID', 'PROFILE']),
      redirectUri: 'https://app.example.com/callback',
      nonce: 'nonce-value',
      isRevoked: false,
    );

    $oidcUser = new OidcUser(
      subject: 'user-id',
      preferredUsername: 'testuser',
      email: 'test@example.com',
      emailVerified: true,
      givenName: 'Test',
      familyName: 'User',
      pictureUrl: 'https://cdn.example.com/avatar.png',
      authTime: new DateTimeImmutable('@1700000000'),
    );

    $claims = [
      'sub' => 'user-id',
      'email' => 'test@example.com',
      'email_verified' => true,
    ];

    $this->authorizationServer
      ->expects(self::once())
      ->method('issueAccessToken')
      ->willReturn($expectedResult);

    $this->authCodeRepository
      ->expects(self::once())
      ->method('findByEncryptedCode')
      ->with('auth-code')
      ->willReturn($authCode);
    $this->authCodeRepository
      ->expects(self::never())
      ->method('find');

    $this->oidcUserProvider
      ->expects(self::once())
      ->method('findByIdentifier')
      ->with('user-id')
      ->willReturn($oidcUser);

    $this->claimsBuilder
      ->expects(self::once())
      ->method('buildIdTokenClaims')
      ->with($oidcUser, ['openid', 'profile'])
      ->willReturn($claims);

    $this->idTokenIssuer
      ->expects(self::once())
      ->method('issueIdToken')
      ->with(
        subject: 'user-id',
        audience: 'client-id',
        nonce: 'nonce-value',
        claims: $claims,
      )
      ->willReturn('id-token');

    $this->eventDispatcher
      ->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(TokenIssuedEvent::class));

    $result = ($this->handler)($command);

    self::assertSame('id-token', $result->idToken);
  }

  /**
   * Method testHandleIssuesIdTokenForRefreshToken.
   *
   * Ensures the handler issues an ID token when
   * the refresh_token grant includes the openid scope.
   *
   * @return void no return value
   */
  #[Test]
  public function testHandleIssuesIdTokenForRefreshToken(): void
  {
    $command = new IssueTokenCommand(
      grantType: 'refresh_token',
      clientId: 'client-id',
      clientSecret: 'client-secret',
      refreshToken: 'refresh-token',
    );

    $expectedResult = new IssueTokenResult(
      accessToken: 'access-token',
      tokenType: 'Bearer',
      expiresIn: 3600,
      refreshToken: 'refresh-token',
      scope: null,
    );

    $refreshToken = new RefreshToken(
      identifier: 'refresh-token',
      expiryDateTime: new DateTimeImmutable('+1 hour'),
      accessTokenIdentifier: 'access-token-id',
      clientIdentifier: new OAuthClientIdentifier('client-id'),
    );

    $accessToken = new AccessToken(
      identifier: 'access-token-id',
      clientIdentifier: new OAuthClientIdentifier('client-id'),
      expiry: new DateTimeImmutable('+1 hour'),
      scopes: Scopes::fromArray(['OPENID', 'PROFILE']),
      userIdentifier: 'user-id',
    );

    $oidcUser = new OidcUser(
      subject: 'user-id',
      preferredUsername: 'testuser',
      email: 'test@example.com',
      emailVerified: true,
      givenName: 'Test',
      familyName: 'User',
      pictureUrl: null,
      authTime: new DateTimeImmutable('@1700000000'),
    );

    $claims = [
      'sub' => 'user-id',
      'email' => 'test@example.com',
      'email_verified' => true,
    ];

    $this->authorizationServer
      ->expects(self::once())
      ->method('issueAccessToken')
      ->willReturn($expectedResult);

    $this->refreshTokenRepository
      ->expects(self::once())
      ->method('findByEncryptedToken')
      ->with('refresh-token')
      ->willReturn($refreshToken);
    $this->refreshTokenRepository
      ->expects(self::never())
      ->method('find');

    $this->accessTokenRepository
      ->expects(self::once())
      ->method('find')
      ->with('access-token-id')
      ->willReturn($accessToken);

    $this->oidcUserProvider
      ->expects(self::once())
      ->method('findByIdentifier')
      ->with('user-id')
      ->willReturn($oidcUser);

    $this->claimsBuilder
      ->expects(self::once())
      ->method('buildIdTokenClaims')
      ->with($oidcUser, Scopes::fromArray(['OPENID', 'PROFILE'])->toArray())
      ->willReturn($claims);

    $this->idTokenIssuer
      ->expects(self::once())
      ->method('issueIdToken')
      ->with(
        subject: 'user-id',
        audience: 'client-id',
        nonce: null,
        claims: $claims,
      )
      ->willReturn('id-token');

    $this->eventDispatcher
      ->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(TokenIssuedEvent::class));

    $result = ($this->handler)($command);

    self::assertSame('id-token', $result->idToken);
  }

  /**
   * Method testHandleSkipsIdTokenWithoutOpenIdScope.
   *
   * Ensures the handler does not issue an ID token
   * when the openid scope is not granted.
   *
   * @return void no return value
   */
  #[Test]
  public function testHandleSkipsIdTokenWithoutOpenIdScope(): void
  {
    $command = new IssueTokenCommand(
      grantType: 'authorization_code',
      clientId: 'client-id',
      clientSecret: 'client-secret',
      scope: 'profile email',
      code: 'auth-code',
      redirectUri: 'https://app.example.com/callback',
      codeVerifier: 'verifier',
    );

    $expectedResult = new IssueTokenResult(
      accessToken: 'access-token',
      tokenType: 'Bearer',
      expiresIn: 3600,
      refreshToken: 'refresh-token',
      scope: 'profile email',
    );

    $authCode = new AuthCode(
      identifier: 'auth-code',
      expiryDateTime: new DateTimeImmutable('+10 minutes'),
      clientIdentifier: new OAuthClientIdentifier('client-id'),
      userIdentifier: 'user-id',
      scopes: Scopes::fromArray(['PROFILE', 'EMAIL']),
      redirectUri: 'https://app.example.com/callback',
      nonce: null,
      isRevoked: false,
    );

    $this->authorizationServer
      ->expects(self::once())
      ->method('issueAccessToken')
      ->willReturn($expectedResult);

    $this->authCodeRepository
      ->expects(self::once())
      ->method('findByEncryptedCode')
      ->with('auth-code')
      ->willReturn($authCode);
    $this->authCodeRepository
      ->expects(self::never())
      ->method('find');

    $this->oidcUserProvider
      ->expects(self::never())
      ->method('findByIdentifier');

    $this->claimsBuilder
      ->expects(self::never())
      ->method('buildIdTokenClaims');

    $this->idTokenIssuer
      ->expects(self::never())
      ->method('issueIdToken');

    $this->eventDispatcher
      ->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(TokenIssuedEvent::class));

    $result = ($this->handler)($command);

    self::assertNull($result->idToken);
  }
  // #endregion
}
