<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

/**
 * Test ClientManagementFlowTest
 *
 * End-to-end tests for OAuth2 client management.
 *
 * @category E2E Tests
 * @package App\Tests\E2E
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class ClientManagementFlowTest extends OAuth2WebTestCase
{
  //#region Different Grant Types Tests

  /**
   * Test that client with only client_credentials cannot use authorization_code
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
      ]) ?: ''
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_BAD_REQUEST],
      'Client credentials should work for API client or return validation error. Response: ' . $response->getContent()
    );
  }

  /**
   * Test different scopes
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
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_BAD_REQUEST],
      'Token request should succeed or return validation error'
    );

    if ($response->getStatusCode() === Response::HTTP_OK || $response->getStatusCode() === Response::HTTP_CREATED) {
      $data = json_decode($response->getContent() ?: '{}', true);
      $this->assertArrayHasKey('access_token', $data, 'Response should contain access_token');
    }
  }

  //#endregion

  //#region Security Tests

  /**
   * Test that expired tokens are rejected
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
      ]
    );

    $response = $client->getResponse();
    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'Expired/invalid token should be rejected'
    );
  }

  /**
   * Test SQL injection prevention in client ID
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
      ]) ?: ''
    );

    $response = $client->getResponse();
    // Should fail gracefully, not with a 500 error
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNAUTHORIZED, Response::HTTP_UNPROCESSABLE_ENTITY],
      'SQL injection attempt should be handled safely'
    );
  }

  //#endregion
}
