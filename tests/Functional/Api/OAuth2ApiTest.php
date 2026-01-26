<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;
use function json_encode;

/**
 * Test OAuth2ApiTest.
 *
 * @category Functional Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OAuth2ApiTest extends WebTestCase
{
  // #region Properties
  private ?KernelBrowser $client = null;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    $this->client = static::createClient();
  }
  // #endregion

  // #region Token Endpoint Tests
  /**
   * Method testTokenEndpointAcceptsPost.
   *
   * Tests that the token endpoint accepts POST requests.
   */
  public function testTokenEndpointAcceptsPost(): void
  {
    $this->client?->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => 'test',
        'client_secret' => 'test',
      ]) ?: '',
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertNotSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
  }

  /**
   * Method testTokenEndpointRejectsGet.
   *
   * Tests that the token endpoint rejects GET requests.
   */
  public function testTokenEndpointRejectsGet(): void
  {
    $this->client?->request(
      method: 'GET',
      uri: '/api/oauth2/token',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
  }

  /**
   * Method testTokenEndpointValidatesClientCredentials.
   *
   * Tests that the token endpoint validates client credentials.
   */
  public function testTokenEndpointValidatesClientCredentials(): void
  {
    $this->client?->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => 'invalid-client',
        'client_secret' => 'invalid-secret',
      ]) ?: '',
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    // Should fail with invalid credentials
    $this->assertContains($response->getStatusCode(), [
      Response::HTTP_BAD_REQUEST,
      Response::HTTP_UNAUTHORIZED,
      Response::HTTP_UNPROCESSABLE_ENTITY,
    ]);
  }
  // #endregion

  // #region Introspection Endpoint Tests
  /**
   * Method testIntrospectionEndpointExists.
   *
   * Tests that the introspection endpoint exists.
   */
  public function testIntrospectionEndpointExists(): void
  {
    $this->client?->request(
      method: 'POST',
      uri: '/api/oauth2/token/introspect',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode(['token' => 'test']) ?: '',
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertNotSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
  }
  // #endregion

  // #region Revocation Endpoint Tests
  /**
   * Method testRevocationEndpointExists.
   *
   * Tests that the revocation endpoint exists.
   */
  public function testRevocationEndpointExists(): void
  {
    $this->client?->request(
      method: 'POST',
      uri: '/api/oauth2/token/revoke',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode(['token' => 'test']) ?: '',
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertNotSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
  }
  // #endregion

  // #region Logout Endpoint Tests
  /**
   * Method testLogoutEndpointReturnsJson.
   *
   * Tests that the logout endpoint returns a JSON response without redirect.
   */
  public function testLogoutEndpointReturnsJson(): void
  {
    $this->client?->request(
      method: 'GET',
      uri: '/api/oauth2/logout',
      server: ['HTTP_ACCEPT' => 'application/json'],
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

    $body = json_decode((string) $response->getContent(), true);
    $this->assertIsArray($body);
    $this->assertTrue($body['logged_out'] ?? false);
  }

  /**
   * Method testLogoutEndpointRejectsInvalidIdTokenHint.
   *
   * Tests that an invalid id_token_hint returns invalid_request.
   */
  public function testLogoutEndpointRejectsInvalidIdTokenHint(): void
  {
    $this->client?->request(
      method: 'GET',
      uri: '/api/oauth2/logout',
      server: ['HTTP_ACCEPT' => 'application/json'],
      parameters: [
        'post_logout_redirect_uri' => 'https://client.example.com/logout',
        'client_id' => 'client-123',
        'id_token_hint' => 'invalid-token',
      ],
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
  }

  /**
   * Method testLogoutEndpointRequiresClientIdWithRedirect.
   *
   * Tests that post_logout_redirect_uri requires a client_id.
   */
  public function testLogoutEndpointRequiresClientIdWithRedirect(): void
  {
    $this->client?->request(
      method: 'GET',
      uri: '/api/oauth2/logout',
      server: ['HTTP_ACCEPT' => 'application/json'],
      parameters: [
        'post_logout_redirect_uri' => 'https://client.example.com/logout',
      ],
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
  }
  // #endregion

  // #region UserInfo Endpoint Tests
  /**
   * Method testUserInfoEndpointRequiresAuthentication.
   *
   * Tests that the userinfo endpoint requires authentication.
   */
  public function testUserInfoEndpointRequiresAuthentication(): void
  {
    $this->client?->request(
      method: 'GET',
      uri: '/api/oauth2/userinfo',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }

  /**
   * Method testUserInfoEndpointRejectsInvalidToken.
   *
   * Tests that the userinfo endpoint rejects invalid tokens.
   */
  public function testUserInfoEndpointRejectsInvalidToken(): void
  {
    $this->client?->request(
      method: 'GET',
      uri: '/api/oauth2/userinfo',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
      ],
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }
  // #endregion
}
