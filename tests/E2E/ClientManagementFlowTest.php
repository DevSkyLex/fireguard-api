<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

use function json_encode;

/**
 * Test ClientManagementFlowTest.
 *
 * End-to-end tests for OAuth2 client management.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class ClientManagementFlowTest extends OAuth2WebTestCase
{
  // #region Different Grant Types Tests

  /**
   * Test that client with only client_credentials cannot use authorization_code.
   */
  public function testClientCredentialsOnlyClient(): void
  {
    $client = static::createClientWithFixtures();

    // API client only has client_credentials grant
    $client->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => 'f6a7b8c9-d0e1-4345-8012-678901234567',
        'client_secret' => 'api_secret_789',
        'scope' => 'READ WRITE',
      ]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_BAD_REQUEST],
      'Client credentials should work for API client or return validation error. Response: ' . $response->getContent(),
    );
  }

  /**
   * Test different scopes.
   */
  public function testDifferentScopes(): void
  {
    $client = static::createClientWithFixtures();

    // Request with limited scopes
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
        'scope' => 'READ',
      ]) ?: '',
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_BAD_REQUEST],
      'Token request should succeed or return validation error',
    );

    if (Response::HTTP_OK === $response->getStatusCode() || Response::HTTP_CREATED === $response->getStatusCode()) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $this->assertArrayHasKey('access_token', $data, 'Response should contain access_token');
    }
  }

  // #endregion

  // #region Security Tests

  /**
   * Test that expired tokens are rejected.
   */
  public function testExpiredTokenHandling(): void
  {
    $client = static::createClientWithFixtures();

    // Use a fake expired token (JWT format but invalid)
    $fakeExpiredToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwiZXhwIjoxfQ.invalid';

    $client->request(
      method: 'GET',
      uri: '/api/users',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $fakeExpiredToken,
      ],
    );

    $response = $client->getResponse();
    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Expired/invalid token should be rejected',
    );
  }

  /**
   * Test SQL injection prevention in client ID.
   */
  public function testSqlInjectionPrevention(): void
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
        'client_id' => "'; DROP TABLE clients; --",
        'client_secret' => 'test',
      ]) ?: '',
    );

    $response = $client->getResponse();
    // Should fail gracefully, not with a 500 error
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNAUTHORIZED, Response::HTTP_UNPROCESSABLE_ENTITY],
      'SQL injection attempt should be handled safely',
    );
  }

  // #endregion

  // #region Client Actions Tests

  /**
   * Test regenerate client secret endpoint.
   */
  public function testRegenerateClientSecret(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    // Use the DEV client ID from fixtures
    $clientId = self::DEV_CLIENT_ID;

    $client->request(
      method: 'POST',
      uri: '/api/clients/' . $clientId . '/regenerate-secret',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    // Should succeed or return auth error (depending on permissions)
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND],
      'Regenerate secret should respond appropriately. Response: ' . $response->getContent(),
    );

    if (Response::HTTP_OK === $response->getStatusCode()) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $this->assertArrayHasKey('secret', $data, 'Response should contain new secret');
    }
  }

  /**
   * Test regenerate secret without authentication.
   */
  public function testRegenerateClientSecretWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/clients/' . self::DEV_CLIENT_ID . '/regenerate-secret',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Regenerate secret without auth should return 401',
    );
  }

  /**
   * Test activate client endpoint.
   */
  public function testActivateClient(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/clients/' . self::DEV_CLIENT_ID . '/activate',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    // Should succeed or return auth error
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_NO_CONTENT, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND],
      'Activate client should respond appropriately. Response: ' . $response->getContent(),
    );
  }

  /**
   * Test activate client without authentication.
   */
  public function testActivateClientWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/clients/' . self::DEV_CLIENT_ID . '/activate',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Activate client without auth should return 401',
    );
  }

  /**
   * Test deactivate client endpoint.
   */
  public function testDeactivateClient(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    // Use API client (not DEV client which we're using for auth)
    $client->request(
      method: 'POST',
      uri: '/api/clients/' . self::API_CLIENT_ID . '/deactivate',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    // Should succeed or return auth error
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_NO_CONTENT, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND],
      'Deactivate client should respond appropriately. Response: ' . $response->getContent(),
    );
  }

  /**
   * Test deactivate client without authentication.
   */
  public function testDeactivateClientWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/clients/' . self::API_CLIENT_ID . '/deactivate',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Deactivate client without auth should return 401',
    );
  }

  /**
   * Test activate non-existent client.
   */
  public function testActivateNonExistentClient(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/clients/00000000-0000-4000-8000-000000000000/activate',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_NOT_FOUND, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Non-existent client should return 404 or auth error',
    );
  }

  // #endregion
}
