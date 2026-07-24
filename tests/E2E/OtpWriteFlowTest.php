<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function bindec;
use function chr;
use function decbin;
use function floor;
use function hash_hmac;
use function json_encode;
use function ord;
use function pack;
use function rtrim;
use function str_pad;
use function str_split;
use function strlen;
use function strpos;
use function strtoupper;
use function time;

use const STR_PAD_LEFT;

/**
 * Test OtpWriteFlow.
 *
 * E2E coverage for the authenticator-app (TOTP) write operations exposed by the
 * Otp module: confirming a pending enrollment and disabling an active one. Both
 * flows are driven end to end over HTTP as the seeded admin, so the requests
 * route through the ConfirmTotpProcessor and DisableTotpProcessor Presentation
 * processors and their success branches.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @see \Otp\Presentation\Api\Processor\Totp\ConfirmTotpProcessor
 * @see \Otp\Presentation\Api\Processor\Totp\DisableTotpProcessor
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OtpWriteFlowTest extends OAuth2WebTestCase
{
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  private const string LD_JSON = 'application/ld+json';

  /**
   * A pending TOTP secret created via /setup is activated by POSTing a valid
   * code to /confirm, which routes through ConfirmTotpProcessor and returns a
   * 2xx with `success: true`.
   */
  public function testConfirmTotpActivatesPendingEnrollment(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $secret = $this->setupTotp($client, $token);

    $client->request(
      method: 'POST',
      uri: '/api/otp/totp/confirm',
      server: $this->authHeaders($token),
      content: json_encode(['code' => $this->generateTotpCode($secret)]) ?: '',
    );

    $response = $client->getResponse();
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Confirming a pending TOTP enrollment with a valid code should succeed. Response: ' . ($response->getContent() ?: ''),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertTrue($data['success'] ?? null, 'A successful confirmation should report success: true.');
  }

  /**
   * An active TOTP enrollment (set up then confirmed) is deactivated by POSTing
   * a valid code to /disable, which routes through DisableTotpProcessor and
   * returns a 2xx with `success: true`.
   */
  public function testDisableTotpDeactivatesActiveEnrollment(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $secret = $this->setupTotp($client, $token);

    // Disable requires a currently-enabled TOTP, so confirm the pending
    // enrollment first (a fresh code is generated for each request).
    $client->request(
      method: 'POST',
      uri: '/api/otp/totp/confirm',
      server: $this->authHeaders($token),
      content: json_encode(['code' => $this->generateTotpCode($secret)]) ?: '',
    );
    self::assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'TOTP must be confirmed before it can be disabled. Response: ' . ($client->getResponse()->getContent() ?: ''),
    );

    $client->request(
      method: 'POST',
      uri: '/api/otp/totp/disable',
      server: $this->authHeaders($token),
      content: json_encode(['code' => $this->generateTotpCode($secret)]) ?: '',
    );

    $response = $client->getResponse();
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Disabling an active TOTP enrollment with a valid code should succeed. Response: ' . ($response->getContent() ?: ''),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertTrue($data['success'] ?? null, 'A successful disable should report success: true.');
  }

  /**
   * The /confirm route exists and is guarded: an unauthenticated call (with an
   * otherwise well-formed body) is rejected with 401/403, never a 404.
   */
  public function testConfirmTotpRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/otp/totp/confirm',
      server: ['CONTENT_TYPE' => self::LD_JSON, 'HTTP_ACCEPT' => self::LD_JSON],
      content: json_encode(['code' => '000000']) ?: '',
    );

    $this->assertGuarded($client, 'POST /api/otp/totp/confirm');
  }

  /**
   * The /disable route exists and is guarded: an unauthenticated call (with an
   * otherwise well-formed body) is rejected with 401/403, never a 404.
   */
  public function testDisableTotpRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/otp/totp/disable',
      server: ['CONTENT_TYPE' => self::LD_JSON, 'HTTP_ACCEPT' => self::LD_JSON],
      content: json_encode(['code' => '000000']) ?: '',
    );

    $this->assertGuarded($client, 'POST /api/otp/totp/disable');
  }

  // #region Helpers

  /**
   * Creates a pending TOTP enrollment for the authenticated caller and returns
   * the server-generated base32 secret.
   */
  private function setupTotp(KernelBrowser $client, string $token): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/otp/totp/setup',
      server: $this->authHeaders($token),
    );

    $response = $client->getResponse();
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'TOTP setup should succeed. Response: ' . ($response->getContent() ?: ''),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $secret = $data['secret'] ?? null;
    self::assertIsString($secret, 'Setup response should contain a base32 secret.');
    self::assertNotSame('', $secret, 'The TOTP secret must not be empty.');

    return $secret;
  }

  private function loginAndGetUserAccessToken(KernelBrowser $client, string $email, string $password): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: ['CONTENT_TYPE' => self::LD_JSON, 'HTTP_ACCEPT' => self::LD_JSON],
      content: json_encode(['email' => $email, 'password' => $password]) ?: '',
    );

    $response = $client->getResponse();
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'User login should succeed. Response: ' . ($response->getContent() ?: ''),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;
    self::assertIsString($token, 'Login response should contain an access token.');
    self::assertNotSame('', $token, 'The access token must not be empty.');

    return $token;
  }

  /**
   * @return array<string, string>
   */
  private function authHeaders(string $token): array
  {
    return [
      'CONTENT_TYPE' => self::LD_JSON,
      'HTTP_ACCEPT' => self::LD_JSON,
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];
  }

  private function assertGuarded(KernelBrowser $client, string $label): void
  {
    $status = $client->getResponse()->getStatusCode();

    self::assertNotSame(Response::HTTP_NOT_FOUND, $status, $label . ' route should exist (not 404).');
    self::assertContains(
      $status,
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      $label . ' without auth should require authentication. Got: ' . $status,
    );
  }

  /**
   * Computes the current 6-digit TOTP code for a base32 secret, mirroring the
   * server-side TotpAdapter (HMAC-SHA1, 30s period, 6 digits) so the confirm
   * and disable flows verify against a genuinely valid code.
   */
  private function generateTotpCode(string $base32Secret): string
  {
    $secretBytes = $this->base32Decode($base32Secret);
    $counter = (int) floor(time() / 30);
    $counterBytes = pack('J', $counter); // 8-byte big-endian counter.

    $hash = hash_hmac('sha1', $counterBytes, $secretBytes, true);
    $offset = ord($hash[19]) & 0x0F;

    $binary = (
      ((ord($hash[$offset]) & 0x7F) << 24)
      | ((ord($hash[$offset + 1]) & 0xFF) << 16)
      | ((ord($hash[$offset + 2]) & 0xFF) << 8)
      | (ord($hash[$offset + 3]) & 0xFF)
    );

    $otp = $binary % (10 ** 6);

    return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
  }

  private function base32Decode(string $input): string
  {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(rtrim($input, '='));

    $buffer = '';
    foreach (str_split($input) as $char) {
      $value = strpos($alphabet, $char);
      if (false === $value) {
        continue;
      }
      $buffer .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
    }

    $result = '';
    foreach (str_split($buffer, 8) as $byte) {
      if (8 === strlen($byte)) {
        $result .= chr((int) bindec($byte));
      }
    }

    return $result;
  }

  // #endregion
}
