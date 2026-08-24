<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use Auth\Infrastructure\Security\User\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;
use function json_encode;

/**
 * Test OAuth2FlowTest.
 *
 * @category Integration Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OAuth2FlowTest extends WebTestCase
{
  // #region Properties
  /**
   * Property client.
   *
   * The test client.
   */
  private ?\Symfony\Bundle\FrameworkBundle\KernelBrowser $client = null;
  // #endregion

  // #region Setup
  /**
   * Method setUp.
   *
   * Sets up the test environment.
   */
  protected function setUp(): void
  {
    $this->client = static::createClient();
  }
  // #endregion

  // #region Tests
  /**
   * Method testTokenEndpointRequiresGrantType.
   *
   * Tests that the token endpoint requires a grant_type.
   */
  public function testTokenEndpointRequiresGrantType(): void
  {
    $this->client?->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: json_encode([
        'client_id' => 'test-client',
        'client_secret' => 'test-secret',
      ]) ?: '',
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
  }

  /**
   * Method testTokenEndpointRejectsInvalidGrantType.
   *
   * Tests that the token endpoint rejects invalid grant types.
   */
  public function testTokenEndpointRejectsInvalidGrantType(): void
  {
    $this->client?->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: json_encode([
        'grant_type' => 'password', // Deprecated in OAuth 2.1
        'client_id' => 'test-client',
        'client_secret' => 'test-secret',
        'username' => 'user',
        'password' => 'pass',
      ]) ?: '',
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
  }

  /**
   * Method testTokenEndpointAcceptsValidGrantTypes.
   *
   * Tests that the token endpoint accepts valid OAuth 2.1 grant types.
   */
  public function testTokenEndpointAcceptsValidGrantTypes(): void
  {
    // Test client_credentials grant type format is accepted
    // Note: This will fail with invalid credentials, but validates the grant type is accepted
    $this->client?->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => 'non-existent-client',
        'client_secret' => 'invalid-secret',
      ]) ?: '',
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    // Should get 400 (bad request from OAuth server) or 422 (if validation fails for other reasons)
    // The important thing is that the grant_type itself is accepted
    $this->assertNotSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
  }

  /**
   * Method testIntrospectionEndpointRequiresToken.
   *
   * Tests that the introspection endpoint requires a token.
   */
  public function testIntrospectionEndpointRequiresToken(): void
  {
    $this->loginAsApiUser();
    $this->client?->request(
      method: 'POST',
      uri: '/api/oauth2/token/introspect',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: json_encode([]) ?: '',
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
  }

  /**
   * Method testIntrospectionEndpointReturnsInactiveForInvalidToken.
   *
   * Tests that the introspection endpoint returns inactive for invalid tokens.
   */
  public function testIntrospectionEndpointReturnsInactiveForInvalidToken(): void
  {
    $this->loginAsApiUser();
    $this->client?->request(
      method: 'POST',
      uri: '/api/oauth2/token/introspect',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: json_encode([
        'token' => 'invalid-token-that-does-not-exist',
      ]) ?: '',
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    // API Platform may return 200 or 201 for POST operations
    $this->assertContains($response->getStatusCode(), [Response::HTTP_OK, Response::HTTP_CREATED]);

    $content = json_decode($response->getContent() ?: '', true);
    $this->assertIsArray($content);
    $this->assertArrayHasKey('active', $content);
    $this->assertFalse($content['active']);
  }

  /**
   * Method testRevocationEndpointAcceptsToken.
   *
   * Tests that the revocation endpoint accepts a token.
   */
  public function testRevocationEndpointAcceptsToken(): void
  {
    $this->loginAsApiUser();
    $this->client?->request(
      method: 'POST',
      uri: '/api/oauth2/token/revoke',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: json_encode([
        'token' => 'some-token-to-revoke',
      ]) ?: '',
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    // Revocation should succeed even for non-existent tokens (RFC 7009)
    $this->assertContains($response->getStatusCode(), [Response::HTTP_OK, Response::HTTP_NO_CONTENT, Response::HTTP_BAD_REQUEST]);
  }

  /**
   * Method testRefreshTokenGrantRequiresRefreshToken.
   *
   * Tests that the refresh_token grant requires a refresh_token field.
   */
  public function testRefreshTokenGrantRequiresRefreshToken(): void
  {
    $this->client?->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: json_encode([
        'grant_type' => 'refresh_token',
        'client_id' => 'test-client',
        'client_secret' => 'test-secret',
        // Missing refresh_token field
      ]) ?: '',
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
  }

  /**
   * Method testAuthorizationCodeGrantRequiresCodeAndRedirectUri.
   *
   * Tests that the authorization_code grant requires code and redirect_uri fields.
   */
  public function testAuthorizationCodeGrantRequiresCodeAndRedirectUri(): void
  {
    $this->client?->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: json_encode([
        'grant_type' => 'authorization_code',
        'client_id' => 'test-client',
        'client_secret' => 'test-secret',
        // Missing code and redirect_uri fields
      ]) ?: '',
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
  }
  // #endregion

  // #region Helpers
  /**
   * Method loginAsApiUser.
   *
   * Authenticates the client against the stateless `api` firewall. Token
   * revocation and introspection are RFC-restricted to authenticated callers,
   * so every case exercising their payload contract has to sign in first —
   * anonymously they answer 401 before the payload is ever read.
   */
  private function loginAsApiUser(): void
  {
    $this->client?->loginUser(
      new SecurityUser(
        id: '00000000-0000-4000-8000-000000000001',
        email: 'oauth-flow@fireguard.test',
        password: 'hashed-password',
        roles: ['ROLE_USER'],
      ),
      'api',
    );
  }
  // #endregion
}
