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
 * Test EquipmentPresentation.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentPresentationFlowTest extends OAuth2WebTestCase
{
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  /**
   * A random UUID used purely to probe route existence on unauthenticated
   * requests. Security is evaluated before the provider runs, so a
   * non-existent identifier still yields 401/403 rather than 404.
   */
  private const string PLACEHOLDER_UUID = '550e8400-e29b-41d4-a716-446655440000';

  // #region Equipment type catalog (equipment_types_list)

  #[Test]
  public function testListEquipmentTypesReturnsCatalog(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $this->authenticatedGet(
      $client,
      $token,
      '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment-types',
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Listing equipment types should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertArrayHasKey('member', $data, 'Type catalog should expose a Hydra member collection.');
    self::assertTrue(is_array($data['member'] ?? null), 'Member collection should be an array.');
  }

  #[Test]
  public function testListEquipmentTypesRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment-types',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $this->assertRouteExistsButGuarded($client->getResponse());
  }

  // #endregion

  // #region Equipment status catalog (equipment_statuses_list)

  #[Test]
  public function testListEquipmentStatusesReturnsCatalog(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $this->authenticatedGet(
      $client,
      $token,
      '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment-statuses',
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Listing equipment statuses should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertArrayHasKey('member', $data, 'Status catalog should expose a Hydra member collection.');
    self::assertTrue(is_array($data['member'] ?? null), 'Member collection should be an array.');
  }

  #[Test]
  public function testListEquipmentStatusesRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment-statuses',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $this->assertRouteExistsButGuarded($client->getResponse());
  }

  // #endregion

  // #region Equipment KPIs (equipment_get_kpis)

  #[Test]
  public function testGetEquipmentKpisReturnsCounters(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $this->authenticatedGet(
      $client,
      $token,
      '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment/kpis',
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Fetching equipment KPIs should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertArrayHasKey('totalAssets', $data, 'KPI payload should expose totalAssets.');
    self::assertArrayHasKey('compliant', $data, 'KPI payload should expose compliant.');
    self::assertArrayHasKey('dueSoon', $data, 'KPI payload should expose dueSoon.');
    self::assertArrayHasKey('openNonConformities', $data, 'KPI payload should expose openNonConformities.');
  }

  #[Test]
  public function testGetEquipmentKpisRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment/kpis',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $this->assertRouteExistsButGuarded($client->getResponse());
  }

  // #endregion

  // #region Tag catalog (equipment_list_tags)

  #[Test]
  public function testListTagCatalogRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment/tags',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $this->assertRouteExistsButGuarded($client->getResponse());
  }

  // #endregion

  // #region Maintenance logs (equipment_list_maintenance_logs)

  #[Test]
  public function testListMaintenanceLogsForSeededEquipment(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $equipmentId = $this->firstSeededEquipmentId($client, $token);
    self::assertNotNull($equipmentId, 'A seeded equipment item should be available.');

    $this->authenticatedGet(
      $client,
      $token,
      '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment/' . $equipmentId . '/maintenance-logs',
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Listing maintenance logs should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertArrayHasKey('member', $data, 'Maintenance log listing should expose a Hydra member collection.');
    self::assertTrue(is_array($data['member'] ?? null), 'Member collection should be an array.');
  }

  #[Test]
  public function testListMaintenanceLogsRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment/' . self::PLACEHOLDER_UUID . '/maintenance-logs',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $this->assertRouteExistsButGuarded($client->getResponse());
  }

  // #endregion

  // #region Facility-scoped equipment (facility_equipment_list)

  #[Test]
  public function testListFacilityEquipmentForSeededFacility(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $facilityId = $this->firstSeededFacilityId($client, $token);
    self::assertNotNull($facilityId, 'A seeded facility should be available.');

    $this->authenticatedGet(
      $client,
      $token,
      '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/facilities/' . $facilityId . '/equipment',
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Listing facility equipment should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertArrayHasKey('member', $data, 'Facility equipment listing should expose a Hydra member collection.');
    self::assertTrue(is_array($data['member'] ?? null), 'Member collection should be an array.');
  }

  #[Test]
  public function testListFacilityEquipmentRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/facilities/' . self::PLACEHOLDER_UUID . '/equipment',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $this->assertRouteExistsButGuarded($client->getResponse());
  }

  // #endregion

  // #region Canonical equipment resource (/api/equipment)

  #[Test]
  public function testCanonicalGetEquipmentItem(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $equipmentId = $this->firstSeededEquipmentId($client, $token);
    self::assertNotNull($equipmentId, 'A seeded equipment item should be available.');

    $this->authenticatedGet($client, $token, '/api/equipment/' . $equipmentId);

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Canonical equipment item fetch should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertSame($equipmentId, $data['id'] ?? null, 'Returned canonical id should match.');
    self::assertArrayHasKey('status', $data, 'Canonical equipment should expose a status.');
    self::assertArrayHasKey('type', $data, 'Canonical equipment should expose a type.');
  }

  #[Test]
  public function testCanonicalGetEquipmentItemRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/equipment/' . self::PLACEHOLDER_UUID,
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $this->assertRouteExistsButGuarded($client->getResponse());
  }

  #[Test]
  public function testCanonicalEquipmentCollectionScopedByOrganization(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $this->authenticatedGet(
      $client,
      $token,
      '/api/equipment?organization=/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID,
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Canonical equipment collection should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertArrayHasKey('member', $data, 'Canonical collection should expose a Hydra member collection.');
    self::assertTrue(is_array($data['member'] ?? null), 'Member collection should be an array.');
  }

  #[Test]
  public function testCanonicalEquipmentCollectionRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/equipment?organization=/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID,
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $this->assertRouteExistsButGuarded($client->getResponse());
  }

  // #endregion

  // #region Write endpoints — auth-guard coverage only

  #[Test]
  public function testUnassignFromFacilityRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment/' . self::PLACEHOLDER_UUID . '/unassign',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: '{}',
    );

    $this->assertRouteExistsButGuarded($client->getResponse());
  }

  #[Test]
  public function testGetMediaRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/media/' . self::PLACEHOLDER_UUID,
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $this->assertRouteExistsButGuarded($client->getResponse());
  }

  #[Test]
  public function testPostMediaRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/media',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $this->assertRouteExistsButGuarded($client->getResponse());
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

  private function assertRouteExistsButGuarded(Response $response): void
  {
    self::assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'Route should exist (not 404). Response: ' . $response->getContent(),
    );
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Unauthenticated access should be rejected with 401/403. Response: ' . $response->getContent(),
    );
  }

  private function firstSeededEquipmentId(KernelBrowser $client, string $token): ?string
  {
    $this->authenticatedGet(
      $client,
      $token,
      '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment?itemsPerPage=1',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');

    return $this->firstMemberId($data);
  }

  private function firstSeededFacilityId(KernelBrowser $client, string $token): ?string
  {
    $this->authenticatedGet(
      $client,
      $token,
      '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/facilities?itemsPerPage=1',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');

    return $this->firstMemberId($data);
  }

  /**
   * @param array<string, mixed> $data
   */
  private function firstMemberId(array $data): ?string
  {
    $members = $data['member'] ?? [];
    if (!is_array($members)) {
      return null;
    }

    foreach ($members as $member) {
      if (!is_array($member)) {
        continue;
      }

      $id = $member['id'] ?? null;
      if (is_string($id) && '' !== $id) {
        return $id;
      }
    }

    return null;
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
