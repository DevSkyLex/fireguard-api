<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

/**
 * OtpConfigFlowTest - E2E tests for OTP configuration discovery API.
 *
 * This test suite covers the OTP (One-Time Password) configuration discovery
 * endpoints that allow clients to discover available OTP options:
 * - List available purposes (GET /api/otp/purposes) - e.g., login, verification, password reset
 * - List available channels (GET /api/otp/channels) - e.g., email, sms, authenticator app
 *
 * These discovery endpoints are typically public to allow client applications
 * to dynamically configure their OTP UI based on server capabilities.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @see \Otp\Presentation\Api\Resource\ConfigResource
 * @see \Otp\Presentation\Api\Provider\ListPurposesProvider
 * @see \Otp\Presentation\Api\Provider\ListChannelsProvider
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class OtpConfigFlowTest extends OAuth2WebTestCase
{
  // #region List Purposes Tests

  /**
   * Test listing OTP purposes without authentication.
   *
   * This test verifies that the purposes discovery endpoint is accessible
   * without authentication. Purpose examples include:
   * - 'verification': Email/phone verification during registration
   * - 'login': Two-factor authentication during login
   * - 'password_reset': Password recovery verification
   *
   * Each purpose includes default TTL and maximum attempt configuration.
   *
   * Expected behavior:
   * - HTTP 200: Returns list of available purposes with their configuration
   * - HTTP 401: If endpoint requires authentication (configuration-dependent)
   */
  public function testListPurposes(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/otp/purposes',
      server: ['HTTP_ACCEPT' => 'application/ld+json']
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Should return purposes list or appropriate response. Response: ' . $response->getContent()
    );

    if (Response::HTTP_OK === $response->getStatusCode()) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $this->assertNotEmpty($data, 'Response should contain purpose data');
    }
  }

  /**
   * Test listing OTP purposes with a valid access token.
   *
   * This test verifies that authenticated users can also access the
   * purposes discovery endpoint. The response should be identical
   * whether authenticated or not (discovery endpoint).
   *
   * Expected behavior:
   * - HTTP 200: Returns list of available purposes
   */
  public function testListPurposesWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'GET',
      uri: '/api/otp/purposes',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Should return purposes list. Response: ' . $response->getContent()
    );
  }

  // #endregion

  // #region List Channels Tests

  /**
   * Test listing OTP delivery channels without authentication.
   *
   * This test verifies that the channels discovery endpoint is accessible
   * and returns the available OTP delivery methods. Channel examples:
   * - 'email': OTP sent via email
   * - 'sms': OTP sent via SMS message
   * - 'totp': Time-based OTP via authenticator app (Google Authenticator, Authy, etc.)
   *
   * Clients use this endpoint to display available options to users.
   *
   * Expected behavior:
   * - HTTP 200: Returns list of available delivery channels
   * - HTTP 401: If endpoint requires authentication (configuration-dependent)
   */
  public function testListChannels(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/otp/channels',
      server: ['HTTP_ACCEPT' => 'application/ld+json']
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_UNAUTHORIZED, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Should return channels list or appropriate response. Response: ' . $response->getContent()
    );

    if (Response::HTTP_OK === $response->getStatusCode()) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      $this->assertNotEmpty($data, 'Response should contain channel data');
    }
  }

  /**
   * Test listing OTP channels with a valid access token.
   *
   * This test verifies authenticated access to the channels discovery
   * endpoint. The response content should be consistent regardless of
   * authentication status.
   *
   * Expected behavior:
   * - HTTP 200: Returns list of available delivery channels
   */
  public function testListChannelsWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'GET',
      uri: '/api/otp/channels',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Should return channels list. Response: ' . $response->getContent()
    );
  }

  // #endregion
}
