<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Response;

use function base64_encode;
use function bin2hex;
use function hash;
use function json_encode;
use function random_bytes;
use function rtrim;
use function strtr;

/**
 * Test AuthorizationCodeFlowTest.
 *
 * End-to-end tests for the Authorization Code flow with PKCE.
 * Note: Some tests may be skipped if the authorize endpoint is not yet implemented.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class AuthorizationCodeFlowTest extends OAuth2WebTestCase
{
  // #region PKCE Helper Methods

  /**
   * Method generateCodeVerifier.
   *
   * Generate a code verifier for PKCE.
   *
   * @since 1.0.0
   *
   * @return string the code verifier
   */
  private function generateCodeVerifier(): string
  {
    return bin2hex(random_bytes(32));
  }

  /**
   * Generate code challenge from verifier (S256 method).
   */
  private function generateCodeChallenge(string $verifier): string
  {
    $hash = hash('sha256', $verifier, true);

    return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
  }

  // #endregion

  // #region Authorization Code Grant Tests

  /**
   * Test authorization code grant requires code parameter.
   */
  public function testAuthorizationCodeGrantRequiresCode(): void
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
        'grant_type' => 'authorization_code',
        'client_id' => self::DEV_CLIENT_ID,
        'client_secret' => self::DEV_CLIENT_SECRET,
        'redirect_uri' => 'https://example.com/callback',
        'code_verifier' => $this->generateCodeVerifier(),
        // Missing 'code' parameter
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
      'Missing code should be rejected'
    );
  }

  /**
   * Test authorization code grant requires redirect_uri.
   */
  public function testAuthorizationCodeGrantRequiresRedirectUri(): void
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
        'grant_type' => 'authorization_code',
        'client_id' => self::DEV_CLIENT_ID,
        'client_secret' => self::DEV_CLIENT_SECRET,
        'code' => 'fake_authorization_code',
        'code_verifier' => $this->generateCodeVerifier(),
        // Missing 'redirect_uri' parameter
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
      'Missing redirect_uri should be rejected'
    );
  }

  /**
   * Test authorization code grant requires code_verifier (PKCE mandatory in OAuth 2.1).
   */
  public function testAuthorizationCodeGrantRequiresCodeVerifier(): void
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
        'grant_type' => 'authorization_code',
        'client_id' => self::DEV_CLIENT_ID,
        'client_secret' => self::DEV_CLIENT_SECRET,
        'code' => 'fake_authorization_code',
        'redirect_uri' => 'https://example.com/callback',
        // Missing 'code_verifier' parameter - PKCE is mandatory
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
      'Missing code_verifier should be rejected (PKCE mandatory)'
    );
  }

  /**
   * Test authorization code grant with invalid code.
   */
  public function testAuthorizationCodeGrantWithInvalidCode(): void
  {
    $client = static::createClientWithFixtures();

    $codeVerifier = $this->generateCodeVerifier();

    $client->request(
      method: 'POST',
      uri: '/api/oauth2/token',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'grant_type' => 'authorization_code',
        'client_id' => self::DEV_CLIENT_ID,
        'client_secret' => self::DEV_CLIENT_SECRET,
        'code' => 'invalid_authorization_code',
        'redirect_uri' => 'https://example.com/callback',
        'code_verifier' => $codeVerifier,
      ]) ?: ''
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_BAD_REQUEST, Response::HTTP_UNAUTHORIZED],
      'Invalid authorization code should be rejected'
    );
  }

  // #endregion

  // #region PKCE Validation Tests

  /**
   * Test PKCE S256 challenge generation is correct.
   */
  public function testPkceS256ChallengeGeneration(): void
  {
    $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
    $expectedChallenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

    $actualChallenge = $this->generateCodeChallenge($verifier);

    $this->assertEquals(
      $expectedChallenge,
      $actualChallenge,
      'PKCE S256 challenge should match RFC 7636 test vector'
    );
  }

  // #endregion
}
