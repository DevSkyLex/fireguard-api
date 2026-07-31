<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Application\UseCase\Command\Session\Login\LoginResult;
use Auth\Application\UseCase\Command\Session\Logout\LogoutResult;
use Auth\Application\UseCase\Query\Session\RefreshToken\RefreshTokenResult;
use Shared\Application\Message\{CommandMessage, QueryMessage, ResultMessage};
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

use function in_array;
use function json_decode;
use function json_encode;
use function sprintf;

/**
 * Test AuthApiTest.
 *
 * @category Functional Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuthApiTest extends WebTestCase
{
  // #region Properties
  private ?KernelBrowser $client = null;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    $this->client = static::createClient();
  }

  protected function tearDown(): void
  {
    $this->client = null;
    self::ensureKernelShutdown();
  }
  // #endregion

  // #region Tests
  public function testLoginEndpointRejectsGet(): void
  {
    $this->client?->request(
      method: 'GET',
      uri: '/api/auth/login',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
  }

  public function testLoginEndpointReturnsUnauthorizedWhenCommandFails(): void
  {
    $this->setCommandBus(LoginResult::failed('Invalid credentials'));

    $this->client?->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => 'user@example.com',
        'password' => 'WrongPassword123!',
      ]) ?: '',
    );

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }

  public function testLoginEndpointReturnsMfaResponse(): void
  {
    $this->setCommandBus(new LoginResult(
      authenticated: true,
      userId: 'user-123',
      email: 'user@example.com',
      mfaRequired: true,
      mfaToken: 'mfa-token',
      challengeToken: 'challenge-token',
    ));

    $this->client?->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => 'user@example.com',
        'password' => 'ValidPassword123!',
      ]) ?: '',
    );

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    $this->assertOkOrCreated($response);

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertTrue($body['mfa_required'] ?? false);
    self::assertSame('mfa-token', $body['mfa_token'] ?? null);
    self::assertSame('challenge-token', $body['challenge_token'] ?? null);
  }

  public function testLoginEndpointReturnsTokensAndSetsCookie(): void
  {
    $this->setCommandBus(new LoginResult(
      authenticated: true,
      userId: 'user-123',
      email: 'user@example.com',
      accessToken: 'access-token',
      refreshToken: 'refresh-token',
      tokenType: 'Bearer',
      expiresIn: 3600,
      scopes: ['openid', 'profile'],
    ));

    $this->client?->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => 'user@example.com',
        'password' => 'ValidPassword123!',
        'remember_me' => false,
      ]) ?: '',
    );

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    $this->assertOkOrCreated($response);

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame('access-token', $body['access_token'] ?? null);
    self::assertSame('Bearer', $body['token_type'] ?? null);
    self::assertSame(3600, $body['expires_in'] ?? null);
    self::assertSame('openid profile', $body['scope'] ?? null);

    $setCookie = $response->headers->get('set-cookie');
    self::assertNotNull($setCookie);
    self::assertStringContainsString('refresh_token', $setCookie);
  }

  public function testRefreshEndpointReturnsUnauthorizedWithoutCookie(): void
  {
    $this->client?->request(
      method: 'POST',
      uri: '/api/auth/refresh',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }

  public function testRefreshEndpointReturnsTokensWhenCookieProvided(): void
  {
    $this->setQueryBus(new RefreshTokenResult(
      success: true,
      userId: 'user-123',
      accessToken: 'new-access',
      refreshToken: 'new-refresh',
      tokenType: 'Bearer',
      expiresIn: 3600,
      scopes: ['openid'],
    ));

    $this->client?->getCookieJar()->set(new Cookie('refresh_token', 'refresh-token'));

    $this->client?->request(
      method: 'POST',
      uri: '/api/auth/refresh',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    $this->assertOkOrCreated($response);

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame('new-access', $body['access_token'] ?? null);
    self::assertSame('Bearer', $body['token_type'] ?? null);
    self::assertSame(3600, $body['expires_in'] ?? null);

    $setCookie = $response->headers->get('set-cookie');
    self::assertNotNull($setCookie);
    self::assertStringContainsString('refresh_token', $setCookie);
  }

  public function testLogoutEndpointClearsCookie(): void
  {
    $this->setCommandBus(new LogoutResult(
      success: true,
      refreshTokenRevoked: true,
      accessTokenRevoked: true,
    ));

    $this->client?->getCookieJar()->set(new Cookie('refresh_token', 'refresh-token'));

    $this->client?->request(
      method: 'POST',
      uri: '/api/auth/logout',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());

    $setCookie = $response->headers->get('set-cookie');
    self::assertNotNull($setCookie);
    self::assertStringContainsString('refresh_token', $setCookie);
  }

  /**
   * The MFA resend endpoint carries its own pre-auth token in the body, so it must
   * stay reachable without an Authorization header — the caller has no access token
   * yet at that point in the login flow.
   *
   * Sending a blank `preAuthToken` separates the two failure modes: a firewall
   * rejection answers 401 before deserialization, whereas a request that actually
   * reaches API Platform fails validation with 422. Anything but 422 here means the
   * route fell back to the `^/api` catch-all again.
   */
  public function testMfaResendEndpointIsReachableWithoutAuthentication(): void
  {
    $this->client?->request(
      method: 'POST',
      uri: '/api/auth/mfa/resend',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode(['preAuthToken' => '']) ?: '',
    );

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    self::assertNotSame(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'POST /api/auth/mfa/resend must not be blocked by the firewall: it is part of the '
        . 'pre-authentication MFA flow and is declared PUBLIC_ACCESS in security.yaml.',
    );
    self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
  }
  // #endregion

  // #region Helpers
  private function setCommandBus(ResultMessage $result): void
  {
    static::getContainer()->set(
      id: CommandBusPort::class,
      service: new TestCommandBus($result),
    );
  }

  private function setQueryBus(ResultMessage $result): void
  {
    static::getContainer()->set(
      id: QueryBusPort::class,
      service: new TestQueryBus($result),
    );
  }

  private function assertOkOrCreated(Response $response): void
  {
    self::assertTrue(
      in_array($response->getStatusCode(), [Response::HTTP_OK, Response::HTTP_CREATED], true),
      sprintf('Expected HTTP 200 or 201, got %d.', $response->getStatusCode()),
    );
  }
  // #endregion
}

final class TestCommandBus implements CommandBusPort
{
  public function __construct(private ResultMessage $result)
  {
  }

  public function dispatch(CommandMessage $command): ResultMessage
  {
    return $this->result;
  }
}

final class TestQueryBus implements QueryBusPort
{
  public function __construct(private ResultMessage $result)
  {
  }

  public function ask(QueryMessage $query): ResultMessage
  {
    return $this->result;
  }
}
