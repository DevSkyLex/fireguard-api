<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Security\Authenticator;

use Auth\Application\Contract\Token\AccessTokenStatus;
use Auth\Application\Port\Outbound\AccessTokenLookupPort;
use Auth\Infrastructure\Security\Authenticator\OAuth2Authenticator;
use Auth\Infrastructure\Security\User\{SecurityUser, SecurityUserProvider};
use Authorization\Application\Port\Inbound\AuthorizationPort;
use DateTimeImmutable;
use InvalidArgumentException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256 as HmacSha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\{AuthenticationException, CustomUserMessageAuthenticationException};
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\GetUserResult;

use function dirname;

/**
 * Test OAuth2AuthenticatorTest.
 *
 * @category Authenticator Tests
 */
#[CoversClass(className: OAuth2Authenticator::class)]
final class OAuth2AuthenticatorTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testSupportsChecksAuthorizationHeader(): void
  {
    $authenticator = $this->createAuthenticator($this->createMock(AccessTokenLookupPort::class));

    $request = new Request();
    $request->headers->set('Authorization', 'Bearer token');

    self::assertTrue($authenticator->supports($request));

    $request = new Request();
    $request->headers->set('Authorization', 'Basic token');

    self::assertFalse($authenticator->supports($request));
  }

  #[Test]
  public function testAuthenticateRejectsEmptyToken(): void
  {
    $authenticator = $this->createAuthenticator($this->createMock(AccessTokenLookupPort::class));

    $request = new Request();
    $request->headers->set('Authorization', 'Bearer ');

    $this->expectException(CustomUserMessageAuthenticationException::class);
    $this->expectExceptionMessage('No access token provided');

    $authenticator->authenticate($request);
  }

  #[Test]
  public function testAuthenticateRejectsMissingClaims(): void
  {
    $authenticator = $this->createAuthenticator($this->createMock(AccessTokenLookupPort::class));
    $token = $this->buildRsaToken(includeJti: true, includeSub: false);

    $request = new Request();
    $request->headers->set('Authorization', 'Bearer ' . $token);

    $this->expectException(CustomUserMessageAuthenticationException::class);
    $this->expectExceptionMessage('Invalid token: missing required claims');

    $authenticator->authenticate($request);
  }

  #[Test]
  public function testAuthenticateWithAccessTokenStatusUsesScopes(): void
  {
    $lookup = $this->createMock(AccessTokenLookupPort::class);
    $lookup->expects(self::once())
      ->method('find')
      ->with('token-123')
      ->willReturn(new AccessTokenStatus(['read', 'write'], false, false));

    $authenticator = $this->createAuthenticator($lookup);
    $token = $this->buildHmacToken();

    $request = new Request();
    $request->headers->set('Authorization', 'Bearer ' . $token);

    $passport = $authenticator->authenticate($request);
    $user = $passport->getUser();

    self::assertInstanceOf(SecurityUser::class, $user);
    self::assertSame(['read', 'write'], $user->getScopes());
  }

  #[Test]
  public function testAuthenticateRejectsRevokedAccessToken(): void
  {
    $lookup = $this->createMock(AccessTokenLookupPort::class);
    $lookup->expects(self::once())
      ->method('find')
      ->with('token-123')
      ->willReturn(new AccessTokenStatus(['read'], true, false));

    $authenticator = $this->createAuthenticator($lookup);
    $token = $this->buildHmacToken();

    $request = new Request();
    $request->headers->set('Authorization', 'Bearer ' . $token);

    $this->expectException(CustomUserMessageAuthenticationException::class);
    $this->expectExceptionMessage('Token has been revoked');

    $authenticator->authenticate($request);
  }

  #[Test]
  public function testAuthenticateRejectsExpiredAccessToken(): void
  {
    $lookup = $this->createMock(AccessTokenLookupPort::class);
    $lookup->expects(self::once())
      ->method('find')
      ->with('token-123')
      ->willReturn(new AccessTokenStatus(['read'], false, true));

    $authenticator = $this->createAuthenticator($lookup);
    $token = $this->buildHmacToken();

    $request = new Request();
    $request->headers->set('Authorization', 'Bearer ' . $token);

    $this->expectException(CustomUserMessageAuthenticationException::class);
    $this->expectExceptionMessage('Token has expired');

    $authenticator->authenticate($request);
  }

  #[Test]
  public function testAuthenticateWithJwtValidationFiltersScopes(): void
  {
    $lookup = $this->createMock(AccessTokenLookupPort::class);
    $lookup->expects(self::once())
      ->method('find')
      ->with('token-123')
      ->willReturn(null);

    $authenticator = $this->createAuthenticator($lookup);
    $token = $this->buildRsaToken(scopes: ['read', 123, 'write', null]);

    $request = new Request();
    $request->headers->set('Authorization', 'Bearer ' . $token);

    $passport = $authenticator->authenticate($request);
    $user = $passport->getUser();

    self::assertInstanceOf(SecurityUser::class, $user);
    self::assertSame(['read', 'write'], $user->getScopes());
  }

  #[Test]
  public function testAuthenticateRejectsInvalidSignature(): void
  {
    $lookup = $this->createMock(AccessTokenLookupPort::class);
    $lookup->expects(self::once())
      ->method('find')
      ->with('token-123')
      ->willReturn(null);

    $authenticator = $this->createAuthenticator($lookup);
    $token = $this->buildHmacToken('other-secret-other-secret-other-secret-1234');

    $request = new Request();
    $request->headers->set('Authorization', 'Bearer ' . $token);

    $this->expectException(CustomUserMessageAuthenticationException::class);
    $this->expectExceptionMessage('Invalid token signature');

    $authenticator->authenticate($request);
  }

  #[Test]
  public function testAuthenticateRejectsExpiredJwt(): void
  {
    $lookup = $this->createMock(AccessTokenLookupPort::class);
    $lookup->expects(self::once())
      ->method('find')
      ->with('token-123')
      ->willReturn(null);

    $authenticator = $this->createAuthenticator($lookup);
    $token = $this->buildRsaToken(expiresAt: new DateTimeImmutable('-10 minutes'));

    $request = new Request();
    $request->headers->set('Authorization', 'Bearer ' . $token);

    $this->expectException(CustomUserMessageAuthenticationException::class);
    $this->expectExceptionMessage('Token has expired');

    $authenticator->authenticate($request);
  }

  #[Test]
  public function testAuthenticateRejectsInvalidTokenString(): void
  {
    $authenticator = $this->createAuthenticator($this->createMock(AccessTokenLookupPort::class));

    $request = new Request();
    $request->headers->set('Authorization', 'Bearer not-a-jwt');

    $this->expectException(CustomUserMessageAuthenticationException::class);
    $this->expectExceptionMessageMatches('/^Invalid access token:/');

    $authenticator->authenticate($request);
  }

  #[Test]
  public function testConstructorThrowsWhenPublicKeyPathEmpty(): void
  {
    $this->expectException(InvalidArgumentException::class);

    new OAuth2Authenticator(
      accessTokenLookup: $this->createMock(AccessTokenLookupPort::class),
      userProvider: $this->createUserProvider(),
      publicKeyPath: '',
    );
  }

  #[Test]
  public function testOnAuthenticationSuccessReturnsNull(): void
  {
    $authenticator = $this->createAuthenticator($this->createMock(AccessTokenLookupPort::class));

    $result = $authenticator->onAuthenticationSuccess(new Request(), $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class), 'main');

    self::assertNull($result);
  }

  #[Test]
  public function testOnAuthenticationFailureReturnsJsonResponse(): void
  {
    $authenticator = $this->createAuthenticator($this->createMock(AccessTokenLookupPort::class));

    $response = $authenticator->onAuthenticationFailure(new Request(), new AuthenticationException('bad token'));

    self::assertSame(401, $response->getStatusCode());
    self::assertSame('Bearer error="invalid_token"', $response->headers->get('WWW-Authenticate'));
  }
  // #endregion

  // #region Helpers
  private function createAuthenticator(AccessTokenLookupPort $lookup): OAuth2Authenticator
  {
    return new OAuth2Authenticator(
      accessTokenLookup: $lookup,
      userProvider: $this->createUserProvider(),
      publicKeyPath: $this->getPublicKeyPath(),
    );
  }

  private function createUserProvider(): SecurityUserProvider
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')
      ->willReturn(new GetUserResult(user: $this->createUserView()));

    $authorization = $this->createMock(AuthorizationPort::class);
    $authorization->method('getUserRoleNames')
      ->willReturn([]);

    return new SecurityUserProvider(
      queryBus: $queryBus,
      authorizationService: $authorization,
    );
  }

  private function createUserView(): UserView
  {
    return new UserView(
      id: 'user-123',
      username: 'user',
      email: 'user@example.com',
      firstName: 'User',
      lastName: 'Example',
      avatarUrl: null,
      status: 'active',
      emailVerified: true,
      tenantId: null,
      createdAt: new DateTimeImmutable('2024-01-01 00:00:00'),
      lastLoginAt: null,
      canLogin: true,
    );
  }

  /**
   * @param list<mixed> $scopes
   */
  private function buildRsaToken(
    bool $includeJti = true,
    bool $includeSub = true,
    array $scopes = [],
    ?DateTimeImmutable $expiresAt = null,
  ): string {
    $config = Configuration::forAsymmetricSigner(
      signer: new Sha256(),
      signingKey: InMemory::file($this->getPrivateKeyPath()),
      verificationKey: InMemory::file($this->getPublicKeyPath()),
    );

    $now = new DateTimeImmutable();
    $builder = $config->builder()
      ->issuedAt($now)
      ->expiresAt($expiresAt ?? $now->modify('+1 hour'));

    if ($includeJti) {
      $builder = $builder->identifiedBy('token-123');
    }

    if ($includeSub) {
      $builder = $builder->relatedTo('user-123');
    }

    if ([] !== $scopes) {
      $builder = $builder->withClaim('scopes', $scopes);
    }

    return $builder->getToken($config->signer(), $config->signingKey())->toString();
  }

  /**
   * @param non-empty-string $secret
   */
  private function buildHmacToken(string $secret = 'secret-secret-secret-secret-secret-1234'): string
  {
    $config = Configuration::forSymmetricSigner(
      signer: new HmacSha256(),
      key: InMemory::plainText($secret),
    );

    $now = new DateTimeImmutable();
    $builder = $config->builder()
      ->issuedAt($now)
      ->expiresAt($now->modify('+1 hour'))
      ->identifiedBy('token-123')
      ->relatedTo('user-123');

    return $builder->getToken($config->signer(), $config->signingKey())->toString();
  }

  private function getProjectDir(): string
  {
    return dirname(__DIR__, 6);
  }

  /**
   * @return non-empty-string
   */
  private function getPublicKeyPath(): string
  {
    return $this->getProjectDir() . '/config/jwt/public.key';
  }

  /**
   * @return non-empty-string
   */
  private function getPrivateKeyPath(): string
  {
    return $this->getProjectDir() . '/config/jwt/private.key';
  }
  // #endregion
}
