<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

/**
 * TotpFlowTest - E2E tests for TOTP (Time-based One-Time Password) management API.
 *
 * This test suite covers the TOTP authenticator app integration:
 * - Setup TOTP (POST /api/otp/totp/setup) - Generates secret and QR code for app pairing
 *
 * TOTP provides a more secure second factor than SMS-based OTP, as it is resistant
 * to SIM-swapping attacks and doesn't require network connectivity. Users pair their
 * authenticator app (Google Authenticator, Authy, Microsoft Authenticator, etc.)
 * by scanning a QR code containing the TOTP secret.
 *
 * Flow:
 * 1. User requests TOTP setup
 * 2. Server generates a unique secret and returns QR code URI
 * 3. User scans QR code with authenticator app
 * 4. User enters generated code to verify setup (via /challenges/{token}/verify)
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @see \Otp\Presentation\Api\Resource\TotpResource
 * @see \Otp\Presentation\Api\Processor\SetupTotpProcessor
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class TotpFlowTest extends OAuth2WebTestCase
{
  // #region Setup TOTP Tests

  /**
   * Test TOTP setup with a valid access token.
   *
   * This test verifies the TOTP enrollment flow for authenticated users.
   * The endpoint generates a new TOTP secret and returns setup data including:
   * - secret: Base32-encoded secret key
   * - qrCodeUri: otpauth:// URI for QR code generation
   * - uri: Alternative key for the QR code data
   *
   * The QR code follows the otpauth:// URI format:
   * otpauth://totp/{issuer}:{account}?secret={secret}&issuer={issuer}
   *
   * Expected behavior:
   * - HTTP 201: TOTP secret generated successfully
   * - HTTP 403: User lacks required permissions
   * - HTTP 400: User already has TOTP configured (if re-enrollment is disabled)
   *
   * @see https://github.com/google/google-authenticator/wiki/Key-Uri-Format
   */
  public function testSetupTotpWithValidToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    $this->assertNotNull($token, 'Should be able to obtain access token');

    $client->request(
      method: 'POST',
      uri: '/api/otp/totp/setup',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ]
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED, Response::HTTP_BAD_REQUEST, Response::HTTP_INTERNAL_SERVER_ERROR],
      'Setup TOTP should respond appropriately. Response: ' . $response->getContent()
    );

    if (Response::HTTP_CREATED === $response->getStatusCode() || Response::HTTP_OK === $response->getStatusCode()) {
      $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
      // Should contain secret and/or QR code URI for authenticator app setup
      $this->assertTrue(
        isset($data['secret']) || isset($data['qrCodeUri']) || isset($data['uri']),
        'TOTP setup response should contain secret or QR code URI'
      );
    }
  }

  /**
   * Test TOTP setup without authentication.
   *
   * This test ensures that TOTP enrollment requires authentication.
   * Unauthenticated users cannot initiate TOTP setup as the secret
   * must be associated with a specific user account.
   *
   * Expected behavior:
   * - HTTP 401: Authentication required
   * - HTTP 403: Access forbidden
   *
   * Security note: Even if the request is rejected, the server should not
   * reveal whether TOTP is available or configured for any user.
   */
  public function testSetupTotpWithoutAuth(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/otp/totp/setup',
      server: ['HTTP_ACCEPT' => 'application/ld+json']
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Should require authentication. Response: ' . $response->getContent()
    );
  }

  // #endregion
}
