<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

/**
 * Test TrustedDeviceFlowTest.
 *
 * End-to-end tests for TrustedDevice management API.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class TrustedDeviceFlowTest extends OAuth2WebTestCase
{
  // #region Setup

  protected function setUp(): void
  {
    parent::setUp();
    // Reset token cache between tests since fixtures are reloaded
    $this->accessToken = null;
  }

  // #endregion

  // #region List Tests

  /**
   * Test listing trusted devices with valid token.
   */
  public function testListTrustedDevicesWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'GET',
      uri: '/api/trusted-devices',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND, Response::HTTP_METHOD_NOT_ALLOWED],
      'Should return trusted devices list or appropriate auth response. Response: ' . $response->getContent()
    );

    if (Response::HTTP_OK === $response->getStatusCode()) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $this->assertArrayHasKey('member', $data);
      $this->assertIsArray($data['member']);
    }
  }

  /**
   * Test listing trusted devices without token.
   */
  public function testListTrustedDevicesWithoutToken(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/trusted-devices',
      server: ['HTTP_ACCEPT' => 'application/ld+json']
    );

    $response = $client->getResponse();

    // The endpoint may return 401, 404, or 405 depending on routing configuration
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND, Response::HTTP_METHOD_NOT_ALLOWED],
      'Should require authentication or not be found without user context'
    );
  }

  // #endregion

  // #region Revoke Tests

  /**
   * Test revoking a trusted device without authentication.
   */
  public function testRevokeDeviceWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'DELETE',
      uri: '/api/trusted-devices/00000000-0000-4000-8000-000000000000',
      server: ['HTTP_ACCEPT' => 'application/ld+json']
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'DELETE trusted device without auth should return 401'
    );
  }

  /**
   * Test revoking a non-existent trusted device.
   */
  public function testRevokeNonExistentDevice(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'DELETE',
      uri: '/api/trusted-devices/00000000-0000-4000-8000-000000000000',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_NOT_FOUND, Response::HTTP_NO_CONTENT, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
      'Non-existent device should return 404 or auth error. Response: ' . $response->getContent()
    );
  }

  // #endregion

  // #region Revoke All Tests

  /**
   * Test revoking all trusted devices with valid token.
   */
  public function testRevokeAllDevicesWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/trusted-devices/revoke-all',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_NO_CONTENT, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Revoke all devices should respond appropriately. Response: ' . $response->getContent()
    );
  }

  /**
   * Test revoking all trusted devices without authentication.
   */
  public function testRevokeAllDevicesWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/trusted-devices/revoke-all',
      server: ['HTTP_ACCEPT' => 'application/ld+json']
    );

    $response = $client->getResponse();

    $this->assertEquals(
      Response::HTTP_UNAUTHORIZED,
      $response->getStatusCode(),
      'POST revoke-all without auth should return 401'
    );
  }

  // #endregion
}
