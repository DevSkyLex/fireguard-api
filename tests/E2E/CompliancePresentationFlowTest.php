<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function basename;
use function is_array;
use function is_string;
use function json_encode;
use function str_contains;
use function uniqid;

/**
 * Test CompliancePresentationFlow.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CompliancePresentationFlowTest extends OAuth2WebTestCase
{
  private const string PLACEHOLDER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440000';

  private const string PLACEHOLDER_FACILITY_ID = '550e8400-e29b-41d4-a716-446655440111';

  /**
   * GET /api/organizations/{organizationId}/compliance — organization rollup.
   */
  public function testOrganizationComplianceOverviewReturnsRegisterForAuthorizedOwner(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'compliance-owner-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Compliance Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/compliance',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Owner should read the organization compliance register. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey('generatedAt', $data);
    $this->assertArrayHasKey('organizationStatus', $data);
    $this->assertArrayHasKey('totals', $data);
    $this->assertArrayHasKey('facilities', $data);
    $this->assertTrue(is_string($data['organizationStatus'] ?? null), 'organizationStatus should be a string verdict.');
    $this->assertTrue(is_array($data['totals'] ?? null), 'totals should be an object of counts.');
    $this->assertTrue(is_array($data['facilities'] ?? null), 'facilities should be an iterable list.');
  }

  /**
   * GET /api/organizations/{organizationId}/facilities/{facilityId}/compliance — single facility detail.
   */
  public function testFacilityComplianceDetailReturnsRegisterForAuthorizedOwner(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'compliance-facility-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Compliance Facility Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $facilityId = $this->createFacility($client, $ownerToken, $organizationId, 'Compliance Building ' . uniqid());
    $this->assertNotNull($facilityId, 'Facility should be created successfully.');

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/facilities/' . $facilityId . '/compliance',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Owner should read the single-facility compliance detail. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey('generatedAt', $data);
    $this->assertArrayHasKey('organizationStatus', $data);
    $this->assertArrayHasKey('totals', $data);
    $this->assertArrayHasKey('facilities', $data);
    $this->assertSame($facilityId, $data['facilityId'] ?? null, 'facilityId should echo the requested facility.');
    $this->assertTrue(is_array($data['facilities'] ?? null), 'facilities should be an iterable list.');
  }

  /**
   * GET /api/organizations/{organizationId}/facility-tree — enriched hierarchy.
   */
  public function testFacilityTreeReturnsHierarchyForAuthorizedOwner(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'compliance-tree-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Compliance Tree Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    // Give the tree at least one node so the enriched shape is exercised.
    $facilityId = $this->createFacility($client, $ownerToken, $organizationId, 'Tree Building ' . uniqid());
    $this->assertNotNull($facilityId, 'Facility should be created successfully.');

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/facility-tree',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Owner should read the enriched facility tree. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey('generatedAt', $data);
    $this->assertArrayHasKey('nodes', $data);
    $this->assertTrue(is_array($data['nodes'] ?? null), 'nodes should be an iterable list of root facility nodes.');
  }

  /**
   * GET /api/organizations/{organizationId}/compliance/export — reachable + entitlement-gated for the owner.
   *
   * The PDF export requires organization.compliance.export AND a pro/max plan;
   * a freshly created organization is not necessarily entitled, so the reliable
   * authenticated assertion is that the route is reachable (not 404) and either
   * renders (200) or is denied by the plan gate (403) — never an auth failure.
   */
  public function testOrganizationSafetyRegisterExportIsReachableAndGatedForOwner(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'compliance-export-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Compliance Export Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/compliance/export',
      server: [
        'HTTP_ACCEPT' => 'application/pdf',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_FORBIDDEN],
      'Export must be reachable for the owner and either render or be plan-gated. Response: ' . $response->getContent(),
    );
  }

  /**
   * Every Compliance Presentation route is guarded: no auth → not 404, and 401/403.
   */
  public function testComplianceEndpointsRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $organizationId = self::PLACEHOLDER_ORGANIZATION_ID;
    $facilityId = self::PLACEHOLDER_FACILITY_ID;

    $routes = [
      '/api/organizations/' . $organizationId . '/compliance',
      '/api/organizations/' . $organizationId . '/facilities/' . $facilityId . '/compliance',
      '/api/organizations/' . $organizationId . '/facility-tree',
      '/api/organizations/' . $organizationId . '/compliance/export',
      '/api/organizations/' . $organizationId . '/facilities/' . $facilityId . '/compliance/export',
    ];

    foreach ($routes as $uri) {
      $client->request(
        method: 'GET',
        uri: $uri,
        server: [
          'HTTP_ACCEPT' => 'application/ld+json',
        ],
      );

      $status = $client->getResponse()->getStatusCode();

      $this->assertNotSame(
        Response::HTTP_NOT_FOUND,
        $status,
        'Route should exist (not 404): ' . $uri,
      );
      $this->assertContains(
        $status,
        [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
        'Route should be guarded when unauthenticated: ' . $uri,
      );
    }
  }

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

    return $this->extractResourceId($this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}'));
  }

  private function createFacility(KernelBrowser $client, string $token, string $organizationId, string $name): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facilities',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'type' => 'building',
        'name' => $name,
      ]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Facility creation should succeed. Response: ' . $response->getContent(),
    );

    return $this->extractResourceId($this->decodeJsonResponse($response->getContent() ?: '{}'));
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
