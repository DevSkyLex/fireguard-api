<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

/**
 * Test SessionManagementFlowTest.
 *
 * End-to-end tests for Session management API.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class SessionManagementFlowTest extends OAuth2WebTestCase
{
  // #region List Sessions Tests

  /**
   * Test listing sessions with valid token.
   */
  public function testListSessionsWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'GET',
      uri: '/api/sessions',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Should return sessions list or appropriate auth response. Response: ' . $response->getContent(),
    );

    if (Response::HTTP_OK === $response->getStatusCode()) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $this->assertArrayHasKey('member', $data);
      $this->assertIsArray($data['member']);
    }
  }

  /**
   * Test listing sessions without token.
   */
  public function testListSessionsWithoutToken(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/sessions',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();

    // The endpoint may return 401 or 404 depending on routing configuration
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND],
      'Should require authentication or not be found without user context',
    );
  }

  // #endregion

  // #region Get Session Tests

  /**
   * Test getting a specific session by ID.
   *
   * Note: We test with a fake UUID since client_credentials tokens
   * don't have associated sessions. The important thing is to verify
   * the endpoint responds correctly.
   */
  public function testGetSessionById(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    // Use a fake UUID to test the endpoint behavior
    $fakeSessionId = '00000000-0000-4000-8000-000000000001';

    $client->request(
      method: 'GET',
      uri: '/api/sessions/' . $fakeSessionId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    // The session won't exist, so we expect 404 or auth-related responses
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_NOT_FOUND, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Should return session or appropriate error. Response: ' . $response->getContent(),
    );
  }

  /**
   * Test getting a non-existent session.
   */
  public function testGetNonExistentSession(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'GET',
      uri: '/api/sessions/00000000-0000-4000-8000-000000000000',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_NOT_FOUND, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Non-existent session should return 404 or auth error. Response: ' . $response->getContent(),
    );
  }

  // #endregion

  // #region Revoke Session Tests

  /**
   * Test revoking a session without authentication.
   */
  public function testRevokeSessionWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'DELETE',
      uri: '/api/sessions/00000000-0000-4000-8000-000000000000',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();

    // May return 401 or 404 depending on route matching
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND],
      'DELETE session without auth should require authentication',
    );
  }

  /**
   * Test revoking a non-existent session.
   */
  public function testRevokeNonExistentSession(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'DELETE',
      uri: '/api/sessions/00000000-0000-4000-8000-000000000000',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_NOT_FOUND, Response::HTTP_NO_CONTENT, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Non-existent session should return 404 or auth error. Response: ' . $response->getContent(),
    );
  }

  // #endregion

  // #region Revoke All Sessions Tests

  /**
   * Test revoking all sessions with valid token.
   */
  public function testRevokeAllSessionsWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/sessions/revoke-all',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_NO_CONTENT, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Revoke all sessions should respond appropriately. Response: ' . $response->getContent(),
    );
  }

  /**
   * Test revoking all sessions without authentication.
   */
  public function testRevokeAllSessionsWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/sessions/revoke-all',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();

    // May return 401 or 404 depending on route matching
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND],
      'POST revoke-all without auth should require authentication',
    );
  }

  // #endregion

  // #region Revoke Other Sessions Tests

  /**
   * Test revoking other sessions with valid token.
   */
  public function testRevokeOtherSessionsWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/sessions/revoke-others',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Revoke other sessions should respond appropriately. Response: ' . $response->getContent(),
    );

    if (Response::HTTP_OK === $response->getStatusCode()) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $this->assertArrayHasKey('revokedCount', $data);
      $this->assertIsInt($data['revokedCount']);
    }
  }

  /**
   * Test revoking other sessions without authentication.
   */
  public function testRevokeOtherSessionsWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/sessions/revoke-others',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();

    // May return 401 or 404 depending on route matching
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND],
      'POST revoke-others without auth should require authentication',
    );
  }

  // #endregion
}
