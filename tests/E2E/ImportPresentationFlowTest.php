<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function is_string;
use function json_encode;

/**
 * Test ImportPresentation.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImportPresentationFlowTest extends OAuth2WebTestCase
{
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  /**
   * The list endpoint requires the `organization` query filter; an
   * authenticated request without it is rejected as a bad request (400), never
   * a 404 — proving the route exists and is handled by its provider.
   */
  #[\PHPUnit\Framework\Attributes\Test]
  public function testListImportsRequiresOrganizationFilter(): void
  {
    $client = static::createClientWithFixtures();

    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $client->request(
      method: 'GET',
      uri: '/api/imports',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $this->assertSame(
      Response::HTTP_BAD_REQUEST,
      $client->getResponse()->getStatusCode(),
      'Listing imports without the organization filter should be a 400. Response: ' . $client->getResponse()->getContent(),
    );
  }

  /**
   * Every Import endpoint is guarded by `is_granted('ROLE_USER')`; unauthenticated
   * requests must be rejected (401/403) and the routes must exist (never 404).
   */
  #[\PHPUnit\Framework\Attributes\Test]
  public function testImportEndpointsRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $organizationId = OrganizationFixtures::ORGANIZATION_ID;
    // Any well-formed UUID; auth is enforced before the resource is resolved.
    $placeholderId = '550e8400-e29b-41d4-a716-446655440000';

    // GET collection.
    $client->request(
      method: 'GET',
      uri: '/api/imports?organization=/api/organizations/' . $organizationId,
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );
    $this->assertGuarded($client, 'GET /api/imports (collection)');

    // GET detail.
    $client->request(
      method: 'GET',
      uri: '/api/imports/' . $placeholderId,
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );
    $this->assertGuarded($client, 'GET /api/imports/{id}');

    // POST upload.
    $client->request(
      method: 'POST',
      uri: '/api/imports',
      parameters: [
        'organization' => '/api/organizations/' . $organizationId,
        'kind' => 'equipment',
      ],
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );
    $this->assertGuarded($client, 'POST /api/imports');
  }

  // #region Helpers

  private function assertGuarded(KernelBrowser $client, string $label): void
  {
    $status = $client->getResponse()->getStatusCode();

    $this->assertNotSame(
      Response::HTTP_NOT_FOUND,
      $status,
      $label . ' route should exist (not 404).',
    );
    $this->assertContains(
      $status,
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      $label . ' without auth should require authentication. Got: ' . $status,
    );
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
      content: json_encode([
        'email' => $email,
        'password' => $password,
      ]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'User login should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    $this->assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }

  // #endregion
}
