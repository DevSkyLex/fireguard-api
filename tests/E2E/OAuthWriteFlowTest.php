<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function is_string;
use function json_encode;

/**
 * Test OAuthWriteFlowTest.
 *
 * End-to-end coverage for OAuth2 client write endpoints, driving the
 * Presentation-layer processors through authenticated HTTP requests.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OAuthWriteFlowTest extends OAuth2WebTestCase
{
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  /**
   * A random UUID used purely to probe route existence on unauthenticated
   * requests. Security is evaluated before the processor runs, so a
   * non-existent identifier still yields 401/403 rather than 404.
   */
  private const string PLACEHOLDER_UUID = '550e8400-e29b-41d4-a716-446655440000';

  // #region Deactivate client (DeactivateClientProcessor)

  #[Test]
  public function testDeactivateClientReturnsInactiveClient(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    // Register a fresh, active client to own the full write flow.
    $clientId = $this->registerClient($client, $token);
    self::assertNotNull($clientId, 'A freshly registered client should expose its client_id.');

    $client->request(
      method: 'POST',
      uri: '/api/clients/' . $clientId . '/deactivate',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Deactivating a client should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertSame($clientId, $data['client_id'] ?? null, 'Response should echo the deactivated client id.');
    self::assertFalse($data['is_active'] ?? true, 'A deactivated client should report is_active=false.');
  }

  #[Test]
  public function testDeactivateClientRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/clients/' . self::PLACEHOLDER_UUID . '/deactivate',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
    );

    $response = $client->getResponse();
    self::assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'Route should exist (not 404). Response: ' . $response->getContent(),
    );
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Unauthenticated deactivation should be rejected with 401/403. Response: ' . $response->getContent(),
    );
  }

  // #endregion

  // #region Helpers

  /**
   * Register a new OAuth2 client and return its identifier.
   *
   * Grant types and scopes use the upper-case enum values enforced by the
   * domain value objects (GrantType / Scope).
   */
  private function registerClient(KernelBrowser $client, string $token): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/clients',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: (string) json_encode([
        'client_name' => 'Write Flow Test Client',
        'redirect_uris' => ['https://write-flow.fireguard.local/callback'],
        'grant_types' => ['CLIENT_CREDENTIALS'],
        'scopes' => ['READ', 'WRITE'],
      ]),
    );

    $response = $client->getResponse();
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Registering a client should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $id = $data['client_id'] ?? null;

    return is_string($id) && '' !== $id ? $id : null;
  }

  private function loginAndGetUserAccessToken(KernelBrowser $client, string $email, string $password): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: (string) json_encode([
        'email' => $email,
        'password' => $password,
      ]),
    );

    $response = $client->getResponse();
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'User login should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    self::assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }

  // #endregion
}
