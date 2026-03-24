<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function basename;
use function count;
use function is_array;
use function is_string;
use function json_encode;
use function str_contains;
use function uniqid;

/**
 * End-to-end tests for Equipment management flow.
 *
 * Covers: create, list, get, update, commission, maintenance,
 * decommission, tag management, attachment management, and auth checks.
 */
final class EquipmentFlowTest extends OAuth2WebTestCase
{
  public function testCompleteEquipmentManagementFlow(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'equipment-owner-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);

    // Step 1: Create an organization.
    $organizationId = $this->createOrganization($client, $ownerToken, 'Equipment Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    // Step 2: Create equipment.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/equipment',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([
        'type' => 'fire_extinguisher',
        'brand' => 'Sicli',
        'model' => 'Pro 6',
        'serialNumber' => 'EXT-E2E-' . uniqid(),
        'locationLabel' => 'Floor 1 – Corridor A',
      ]) ?: '',
    );

    $createResponse = $client->getResponse();
    $this->assertContains(
      $createResponse->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Equipment creation should succeed. Response: ' . $createResponse->getContent(),
    );

    $createData = $this->decodeJsonResponse($createResponse->getContent() ?: '{}');
    $equipmentId = $this->extractResourceId($createData);
    $this->assertNotNull($equipmentId, 'Equipment ID should be present in create response.');
    $this->assertSame('fire_extinguisher', $createData['type'] ?? null, 'Type should match.');
    $this->assertSame('in_stock', $createData['status'] ?? null, 'New equipment should be in_stock.');

    // Step 3: List equipment — created item should appear.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/equipment',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $listResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $listResponse->getStatusCode(),
      'Listing equipment should succeed. Response: ' . $listResponse->getContent(),
    );

    $listData = $this->decodeJsonResponse($listResponse->getContent() ?: '{}');
    $members = $this->getCollectionMembers($listData);
    $this->assertTrue(
      $this->collectionContainsId($members, $equipmentId),
      'Created equipment should appear in the list.',
    );

    // Step 4: Get equipment by ID.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/equipment/' . $equipmentId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $getResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $getResponse->getStatusCode(),
      'Getting equipment by ID should succeed. Response: ' . $getResponse->getContent(),
    );

    $getData = $this->decodeJsonResponse($getResponse->getContent() ?: '{}');
    $this->assertSame($equipmentId, $this->extractResourceId($getData), 'Returned ID should match.');

    // Step 5: Update equipment.
    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/equipment/' . $equipmentId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([
        'type' => 'fire_extinguisher',
        'brand' => 'Amerex',
        'model' => 'B402',
        'locationLabel' => 'Floor 2 – Room 201',
      ]) ?: '',
    );

    $updateResponse = $client->getResponse();
    $this->assertContains(
      $updateResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Update equipment should succeed. Response: ' . $updateResponse->getContent(),
    );

    $updateData = $this->decodeJsonResponse($updateResponse->getContent() ?: '{}');
    $this->assertSame('Amerex', $updateData['brand'] ?? null, 'Brand should be updated.');

    // Step 6a: Create a facility (required before commissioning).
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facilities',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([
        'type' => 'building',
        'name' => 'Main Building',
      ]) ?: '',
    );

    $facilityResponse = $client->getResponse();
    $this->assertContains(
      $facilityResponse->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Facility creation should succeed. Response: ' . $facilityResponse->getContent(),
    );

    $facilityData = $this->decodeJsonResponse($facilityResponse->getContent() ?: '{}');
    $facilityId = $this->extractResourceId($facilityData);
    $this->assertNotNull($facilityId, 'Facility ID should be present in create response.');

    // Step 6b: Assign equipment to the facility (required before commissioning).
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/equipment/' . $equipmentId . '/assign',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode(['facilityId' => $facilityId]) ?: '',
    );

    $assignResponse = $client->getResponse();
    $this->assertContains(
      $assignResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Assign to facility should succeed. Response: ' . $assignResponse->getContent(),
    );

    // Step 6: Commission equipment.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/equipment/' . $equipmentId . '/commission',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([]) ?: '',
    );

    $commissionResponse = $client->getResponse();
    $this->assertContains(
      $commissionResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Commission equipment should succeed. Response: ' . $commissionResponse->getContent(),
    );

    $commissionData = $this->decodeJsonResponse($commissionResponse->getContent() ?: '{}');
    $this->assertSame('operational', $commissionData['status'] ?? null, 'Status should be operational after commission.');

    // Step 7: Put under maintenance.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/equipment/' . $equipmentId . '/maintenance',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([]) ?: '',
    );

    $maintenanceResponse = $client->getResponse();
    $this->assertContains(
      $maintenanceResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Put under maintenance should succeed. Response: ' . $maintenanceResponse->getContent(),
    );

    $maintenanceData = $this->decodeJsonResponse($maintenanceResponse->getContent() ?: '{}');
    $this->assertSame('under_maintenance', $maintenanceData['status'] ?? null, 'Status should be under_maintenance.');

    // Step 8: Add a tag.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/equipment/' . $equipmentId . '/tags',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode(['name' => 'inspection-required']) ?: '',
    );

    $addTagResponse = $client->getResponse();
    $this->assertContains(
      $addTagResponse->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Adding a tag should succeed. Response: ' . $addTagResponse->getContent(),
    );

    $addTagData = $this->decodeJsonResponse($addTagResponse->getContent() ?: '{}');
    $tagId = $this->extractResourceId($addTagData);
    $this->assertNotNull($tagId, 'Tag ID should be present after add tag.');
    $this->assertSame('inspection-required', $addTagData['name'] ?? null, 'Tag name should match.');

    // Step 9: Verify tag appears on equipment GET.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/equipment/' . $equipmentId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $getWithTagsData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $tags = $getWithTagsData['tags'] ?? [];
    $this->assertTrue(is_array($tags) && count($tags) > 0, 'Equipment should have tags after adding one.');

    // Step 10: Remove the tag.
    $client->request(
      method: 'DELETE',
      uri: '/api/organizations/' . $organizationId . '/equipment/' . $equipmentId . '/tags/' . $tagId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $removeTagResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_NO_CONTENT,
      $removeTagResponse->getStatusCode(),
      'Removing a tag should return 204. Response: ' . $removeTagResponse->getContent(),
    );

    // Step 11: List attachments (empty).
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/equipment/' . $equipmentId . '/attachments',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $listAttachmentsResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $listAttachmentsResponse->getStatusCode(),
      'Listing attachments should succeed. Response: ' . $listAttachmentsResponse->getContent(),
    );

    // Step 12: Decommission equipment.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/equipment/' . $equipmentId . '/decommission',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([]) ?: '',
    );

    $decommissionResponse = $client->getResponse();
    $this->assertContains(
      $decommissionResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Decommission equipment should succeed. Response: ' . $decommissionResponse->getContent(),
    );

    $decommissionData = $this->decodeJsonResponse($decommissionResponse->getContent() ?: '{}');
    $this->assertSame('decommissioned', $decommissionData['status'] ?? null, 'Status should be decommissioned.');
  }

  public function testCreateEquipmentReturns409OnDuplicateSerialNumber(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'equipment-serial-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Serial Org ' . uniqid());
    $this->assertNotNull($organizationId);

    $serialNumber = 'EXT-UNIQ-' . uniqid();
    $payload = json_encode([
      'type' => 'fire_extinguisher',
      'serialNumber' => $serialNumber,
    ]) ?: '';

    // First creation must succeed.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/equipment',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: $payload,
    );

    $this->assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'First equipment creation should succeed.',
    );

    // Second creation with same serial number must return 409.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/equipment',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: $payload,
    );

    $this->assertSame(
      Response::HTTP_CONFLICT,
      $client->getResponse()->getStatusCode(),
      'Duplicate serial number should return HTTP 409.',
    );
  }

  public function testEquipmentEndpointsRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    // Use a placeholder organization ID (any UUID).
    $organizationId = '550e8400-e29b-41d4-a716-446655440000';

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/equipment',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
    );

    $this->assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Equipment list without auth should require authentication.',
    );
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

  /**
   * @param array<string, mixed> $data
   *
   * @return list<array<string, mixed>>
   */
  private function getCollectionMembers(array $data): array
  {
    $members = $data['member'] ?? [];
    if (!is_array($members)) {
      return [];
    }

    $result = [];
    foreach ($members as $member) {
      if (!is_array($member)) {
        continue;
      }

      $normalized = [];
      foreach ($member as $key => $value) {
        if (is_string($key)) {
          $normalized[$key] = $value;
        }
      }

      $result[] = $normalized;
    }

    return $result;
  }

  /**
   * @param list<array<string, mixed>> $collection
   */
  private function collectionContainsId(array $collection, string $id): bool
  {
    foreach ($collection as $item) {
      if ($id === $this->extractResourceId($item)) {
        return true;
      }
    }

    return false;
  }

  // #endregion
}
