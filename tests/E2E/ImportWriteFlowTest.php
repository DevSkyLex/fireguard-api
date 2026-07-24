<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function is_array;
use function is_string;
use function json_encode;

/**
 * Test ImportWriteFlow.
 *
 * Drives the org-scoped import listing endpoint (`GET /api/imports`) to raise
 * Presentation-layer coverage of {@see \Import\Presentation\Api\Provider\ImportJobCollectionProvider}.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImportWriteFlowTest extends OAuth2WebTestCase
{
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  // #region ImportJobCollectionProvider — GET /api/imports

  /**
   * As the authenticated admin (who holds equipment/facility read on the seeded
   * organization), listing imports scoped by the required `organization` filter
   * succeeds and returns a Hydra collection — exercising the provider's happy
   * path: security check, org resolution, query bus dispatch and paginator build.
   */
  #[Test]
  public function testListImportsReturnsCollectionForSeededOrganization(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $this->authenticatedGet(
      $client,
      $token,
      '/api/imports?organization=/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID,
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Listing imports for the seeded organization should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertArrayHasKey('member', $data, 'Import listing should expose a Hydra member collection.');
    self::assertTrue(is_array($data['member'] ?? null), 'Member collection should be an array.');
  }

  /**
   * The optional `kind` filter and the client pagination parameters route through
   * the provider's kind/page/itemsPerPage parsing branches; equipment is a valid
   * kind the admin may read, so the request still succeeds with a Hydra collection.
   */
  #[Test]
  public function testListImportsFilteredByEquipmentKindWithPagination(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $this->authenticatedGet(
      $client,
      $token,
      '/api/imports?organization=/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID
        . '&kind=equipment&page=1&itemsPerPage=10',
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Listing equipment imports with pagination should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertArrayHasKey('member', $data, 'Filtered import listing should expose a Hydra member collection.');
    self::assertTrue(is_array($data['member'] ?? null), 'Member collection should be an array.');
  }

  /**
   * The collection route is guarded by `is_granted('ROLE_USER')`; an
   * unauthenticated request must be rejected with 401/403 and the route must
   * exist (never 404), covering route wiring for the provider.
   */
  #[Test]
  public function testListImportsRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/imports?organization=/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID,
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();
    self::assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'GET /api/imports route should exist (not 404). Response: ' . $response->getContent(),
    );
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Unauthenticated import listing should be rejected with 401/403. Response: ' . $response->getContent(),
    );
  }

  // #endregion

  // #region Helpers

  private function authenticatedGet(KernelBrowser $client, string $token, string $uri): void
  {
    $client->request(
      method: 'GET',
      uri: $uri,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
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
