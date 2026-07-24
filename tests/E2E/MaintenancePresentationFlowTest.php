<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function array_key_exists;
use function basename;
use function is_string;
use function json_encode;
use function str_contains;
use function uniqid;

/**
 * Test MaintenancePresentation.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenancePresentationFlowTest extends OAuth2WebTestCase
{
  private const string RANDOM_SCHEDULE_ID = '550e8400-e29b-41d4-a716-4466554409f0';

  // #region Tests

  /**
   * Happy path: authenticated GET of the schedule collection (org-scoped)
   * returns 200 with a Hydra collection shape. A freshly created organization
   * has no computed schedules yet, so the collection is legitimately empty —
   * the assertion is on the response structure, not on counts.
   */
  public function testListMaintenanceSchedulesReturnsHydraCollection(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'maintenance-list-' . uniqid() . '@example.com';
    $password = 'Maintenance123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Maintenance List Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $client->request(
      method: 'GET',
      uri: '/api/maintenance/schedules?organization=' . $organizationId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Listing maintenance schedules should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertTrue(array_key_exists('member', $data), 'Hydra collection should expose a "member" key.');
    $this->assertIsArray($data['member'] ?? null, 'The "member" key should be an array.');
    $this->assertArrayHasKey('totalItems', $data, 'Hydra collection should expose "totalItems".');
  }

  /**
   * The schedule collection provider requires an "organization" query filter;
   * an authenticated request omitting it must return 400 (provider guard).
   */
  public function testListMaintenanceSchedulesWithoutOrganizationReturns400(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'maintenance-noorg-' . uniqid() . '@example.com';
    $password = 'Maintenance123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    $client->request(
      method: 'GET',
      uri: '/api/maintenance/schedules',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $this->assertSame(
      Response::HTTP_BAD_REQUEST,
      $client->getResponse()->getStatusCode(),
      'Missing "organization" filter should return HTTP 400.',
    );
  }

  /**
   * Authenticated GET of a non-existent schedule exercises the provider's item
   * path and the domain→HTTP not-found mapping (404). No schedule is seeded, so
   * a random id is deterministically absent.
   */
  public function testGetUnknownMaintenanceScheduleReturns404(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'maintenance-get-' . uniqid() . '@example.com';
    $password = 'Maintenance123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    $client->request(
      method: 'GET',
      uri: '/api/maintenance/schedules/' . self::RANDOM_SCHEDULE_ID,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $this->assertSame(
      Response::HTTP_NOT_FOUND,
      $client->getResponse()->getStatusCode(),
      'Getting an unknown maintenance schedule should return HTTP 404.',
    );
  }

  /**
   * Authenticated PATCH of a non-existent schedule exercises the processor and
   * the domain→HTTP not-found mapping (404) with a valid override body.
   */
  public function testPatchUnknownMaintenanceScheduleReturns404(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'maintenance-patch-' . uniqid() . '@example.com';
    $password = 'Maintenance123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    $client->request(
      method: 'PATCH',
      uri: '/api/maintenance/schedules/' . self::RANDOM_SCHEDULE_ID,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['intervalOverride' => 'P90D']) ?: '',
    );

    $this->assertSame(
      Response::HTTP_NOT_FOUND,
      $client->getResponse()->getStatusCode(),
      'Patching an unknown maintenance schedule should return HTTP 404.',
    );
  }

  /**
   * Authenticated campaign generation with a valid body but no due schedules
   * exercises the processor and the business-rule validation path. A freshly
   * created organization has no due/overdue schedules, so the handler raises a
   * validation error mapped to HTTP 422.
   */
  public function testGenerateCampaignWithNoDueSchedulesReturns422(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'maintenance-campaign-' . uniqid() . '@example.com';
    $password = 'Maintenance123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Maintenance Campaign Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $client->request(
      method: 'POST',
      uri: '/api/maintenance/campaigns',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'organization' => '/api/organizations/' . $organizationId,
        'name' => 'Q3 Campaign ' . uniqid(),
        'dueBefore' => '2026-12-31T00:00:00+00:00',
      ]) ?: '',
    );

    $this->assertSame(
      Response::HTTP_UNPROCESSABLE_ENTITY,
      $client->getResponse()->getStatusCode(),
      'Generating a campaign with no due schedules should return HTTP 422. Response: '
        . $client->getResponse()->getContent(),
    );
  }

  /**
   * Every Maintenance Presentation endpoint must reject unauthenticated access:
   * the route exists (status != 404) and is guarded (401 or 403).
   */
  public function testMaintenanceEndpointsRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();
    $orgId = '550e8400-e29b-41d4-a716-446655440000';

    $endpoints = [
      ['GET', '/api/maintenance/schedules?organization=' . $orgId],
      ['GET', '/api/maintenance/schedules/' . self::RANDOM_SCHEDULE_ID],
      ['PATCH', '/api/maintenance/schedules/' . self::RANDOM_SCHEDULE_ID],
      ['POST', '/api/maintenance/campaigns'],
    ];

    foreach ($endpoints as [$method, $uri]) {
      $client->request(
        method: $method,
        uri: $uri,
        server: [
          'CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json',
          'HTTP_ACCEPT' => 'application/ld+json',
        ],
        content: '{}',
      );

      $statusCode = $client->getResponse()->getStatusCode();

      $this->assertNotSame(
        Response::HTTP_NOT_FOUND,
        $statusCode,
        "Route should exist for {$method} {$uri}, got 404.",
      );
      $this->assertContains(
        $statusCode,
        [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
        "Expected 401/403 for unauthenticated {$method} {$uri}, got {$statusCode}.",
      );
    }
  }

  // #endregion

  // #region Helpers

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

  private function createOrganization(KernelBrowser $client, string $token, string $name): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['name' => $name]) ?: '',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');

    return $this->extractResourceId($data);
  }

  /**
   * @param array<string, mixed> $data
   */
  private function extractResourceId(array $data): ?string
  {
    $id = $data['id'] ?? null;
    if (is_string($id) && '' !== $id) {
      return $id;
    }

    $iri = $data['@id'] ?? null;
    if (is_string($iri) && str_contains($iri, '/')) {
      $candidate = basename($iri);

      return '' !== $candidate ? $candidate : null;
    }

    return null;
  }

  // #endregion
}
