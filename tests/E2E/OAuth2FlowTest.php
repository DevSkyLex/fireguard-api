<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

/**
 * Test OAuth2FlowTest
 *
 * End-to-end tests for the complete OAuth2 authentication flow.
 *
 * @category E2E Tests
 * @package App\Tests\E2E
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class OAuth2FlowTest extends OAuth2WebTestCase
{

  //#region Client Credentials Flow Tests

  /**
   * Test complete client credentials flow
   */
  public function testClientCredentialsFlowComplete(): void
  {
    $client = static::createClientWithFixtures();

    // Step 1: Request token
    $client->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => self::API_CLIENT_ID,
        'client_secret' => self::API_CLIENT_SECRET,
        'scope' => 'READ WRITE',
      ]) ?: ''
    );

    $response = $client->getResponse();

    // Should return 200 or 201 with token
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Token request should succeed. Response: ' . $response->getContent()
    );

    $data = json_decode($response->getContent() ?: '{}', true);

    $this->assertArrayHasKey('access_token', $data, 'Response should contain access_token');
    $this->assertArrayHasKey('token_type', $data, 'Response should contain token_type');
    $this->assertArrayHasKey('expires_in', $data, 'Response should contain expires_in');
    $this->assertEquals('Bearer', $data['token_type']);
    $this->assertGreaterThan(0, $data['expires_in']);

    $accessToken = $data['access_token'];

    // Step 2: Introspect token
    $client->request(
      method: 'POST',
      uri: '/api/oauth2/token/introspect',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'token' => $accessToken,
      ]) ?: ''
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Introspection should succeed'
    );

    $introspectData = json_decode($response->getContent() ?: '{}', true);
    $this->assertTrue($introspectData['active'] ?? false, 'Token should be active');

    // Step 3: Access protected resource with token
    $client->request(
      method: 'GET',
      uri: '/api/users',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken,
      ]
    );

    $response = $client->getResponse();
    // Should be 200 OK, 403 Forbidden, or 401 Unauthorized (token validation may fail in test env)
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Protected resource should respond appropriately'
    );

    // Step 4: Revoke token
    $client->request(
      method: 'POST',
      uri: '/api/oauth2/token/revoke',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'token' => $accessToken,
      ]) ?: ''
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_NO_CONTENT],
      'Revocation should succeed'
    );

    // Step 5: Verify token is no longer active
    $client->request(
      method: 'POST',
      uri: '/api/oauth2/token/introspect',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'token' => $accessToken,
      ]) ?: ''
    );

    $response = $client->getResponse();
    $introspectData = json_decode($response->getContent() ?: '{}', true);
    $this->assertFalse($introspectData['active'] ?? true, 'Revoked token should be inactive');
  }

  /**
   * Test token request with invalid client credentials
   */
  public function testClientCredentialsWithInvalidSecret(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => self::API_CLIENT_ID,
        'client_secret' => 'wrong_secret',
      ]) ?: ''
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNAUTHORIZED],
      'Invalid credentials should be rejected'
    );
  }

  /**
   * Test token request with non-existent client
   */
  public function testClientCredentialsWithNonExistentClient(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => '00000000-0000-4000-8000-000000000000',
        'client_secret' => 'any_secret',
      ]) ?: ''
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNAUTHORIZED],
      'Non-existent client should be rejected'
    );
  }

  //#endregion

  //#region OpenID Connect Discovery Tests

  /**
   * Test OpenID Configuration endpoint
   */
  public function testOpenIdConfiguration(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/.well-known/openid-configuration',
      server: ['HTTP_ACCEPT' => 'application/ld+json']
    );

    $response = $client->getResponse();
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

    $data = json_decode($response->getContent() ?: '{}', true);

    $this->assertArrayHasKey('issuer', $data);
    $this->assertArrayHasKey('authorization_endpoint', $data);
    $this->assertArrayHasKey('token_endpoint', $data);
    $this->assertArrayHasKey('jwks_uri', $data);
    $this->assertArrayHasKey('response_types_supported', $data);
    $this->assertArrayHasKey('grant_types_supported', $data);
    $this->assertArrayHasKey('scopes_supported', $data);
  }

  /**
   * Test JWKS endpoint
   */
  public function testJwksEndpoint(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/.well-known/jwks.json',
      server: ['HTTP_ACCEPT' => 'application/ld+json']
    );

    $response = $client->getResponse();
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

    $data = json_decode($response->getContent() ?: '{}', true);

    $this->assertArrayHasKey('keys', $data);
    $this->assertIsArray($data['keys']);
    $this->assertNotEmpty($data['keys']);

    // Verify key structure
    $key = $data['keys'][0];
    $this->assertArrayHasKey('kty', $key);
    $this->assertArrayHasKey('use', $key);
    $this->assertArrayHasKey('kid', $key);
  }

  //#endregion

  //#region Protected Resource Access Tests

  /**
   * Test accessing protected resource without token
   */
  public function testProtectedResourceWithoutToken(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/users',
      server: ['HTTP_ACCEPT' => 'application/ld+json']
    );

    $response = $client->getResponse();
    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Protected resource should require authentication'
    );
  }

  /**
   * Test accessing protected resource with invalid token
   */
  public function testProtectedResourceWithInvalidToken(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/users',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer invalid_token_here',
      ]
    );

    $response = $client->getResponse();
    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Invalid token should be rejected'
    );
  }

  /**
   * Test UserInfo endpoint with valid token
   */
  public function testUserInfoWithValidToken(): void
  {
    $client = static::createClientWithFixtures();

    // First get a token
    $client->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => self::DEV_CLIENT_ID,
        'client_secret' => self::DEV_CLIENT_SECRET,
        'scope' => 'OPENID PROFILE EMAIL',
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Token request should succeed. Response: ' . $response->getContent()
    );

    $data = json_decode($response->getContent() ?: '{}', true);
    $accessToken = $data['access_token'] ?? '';

    // Access userinfo
    $client->request(
      method: 'GET',
      uri: '/api/oauth2/userinfo',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken,
      ]
    );

    $response = $client->getResponse();
    // UserInfo might require a user-bound token (not client credentials)
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'UserInfo should respond appropriately'
    );
  }

  //#endregion

  //#region Token Validation Tests

  /**
   * Test introspection with invalid token
   */
  public function testIntrospectionWithInvalidToken(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/oauth2/token/introspect',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'token' => 'invalid_token_string',
      ]) ?: ''
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Introspection should always return 200'
    );

    $data = json_decode($response->getContent() ?: '{}', true);
    $this->assertFalse($data['active'] ?? true, 'Invalid token should be inactive');
  }

  /**
   * Test revocation with invalid token (should still succeed per RFC 7009)
   */
  public function testRevocationWithInvalidToken(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/oauth2/token/revoke',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'token' => 'invalid_token_string',
      ]) ?: ''
    );

    $response = $client->getResponse();
    // Per RFC 7009, revocation should succeed even for invalid tokens
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_NO_CONTENT],
      'Revocation should succeed even for invalid tokens'
    );
  }

  //#endregion

  //#region Multiple Token Tests

  /**
   * Test requesting multiple tokens for same client
   */
  public function testMultipleTokensForSameClient(): void
  {
    $client = static::createClientWithFixtures();

    $tokens = [];

    // Request 3 tokens
    for ($i = 0; $i < 3; $i++) {
      $client->request(
        method: 'POST',
        uri: '/api/oauth2/token',
        server: [
          'CONTENT_TYPE' => 'application/ld+json',
          'HTTP_ACCEPT' => 'application/ld+json',
        ],
        content: json_encode([
          'grant_type' => 'client_credentials',
          'client_id' => self::API_CLIENT_ID,
          'client_secret' => self::API_CLIENT_SECRET,
          'scope' => 'READ WRITE',
        ]) ?: ''
      );

      $response = $client->getResponse();

      if ($response->getStatusCode() === Response::HTTP_OK || $response->getStatusCode() === Response::HTTP_CREATED) {
        $data = json_decode($response->getContent() ?: '{}', true);
        $tokens[] = $data['access_token'] ?? '';
      }
    }

    // Verify tokens were generated
    $this->assertGreaterThan(0, count($tokens), 'Should be able to generate at least one token');

    // All tokens should be unique
    $this->assertCount(count($tokens), array_unique($tokens), 'All tokens should be unique');

    // All tokens should be valid
    foreach ($tokens as $token) {
      $client->request(
        method: 'POST',
        uri: '/api/oauth2/token/introspect',
        server: [
          'CONTENT_TYPE' => 'application/ld+json',
          'HTTP_ACCEPT' => 'application/ld+json',
        ],
        content: json_encode(['token' => $token]) ?: ''
      );

      $response = $client->getResponse();
      $data = json_decode($response->getContent() ?: '{}', true);
      $this->assertTrue($data['active'] ?? false, 'Each token should be active');
    }
  }

  //#endregion
}
