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
 * Test InspectionPresentationFlow.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionPresentationFlowTest extends OAuth2WebTestCase
{
  // #region Tests

  /**
   * The organization-wide non-conformity list and the single non-conformity
   * detail endpoints exercise the ListOrganizationNonConformitiesProvider and
   * GetNonConformityProvider.
   */
  public function testGetNonConformityAndOrganizationNonConformitiesFlow(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'nc-detail-' . uniqid() . '@example.com';
    $password = 'NcDetail123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'NC Detail Org ' . uniqid());
    $this->assertNotNull($organizationId);

    $equipmentId = $this->createEquipment($client, $token, $organizationId);
    $this->assertNotNull($equipmentId);

    $inspectionId = $this->createDraftInspection($client, $token, $organizationId, $equipmentId);
    $this->assertNotNull($inspectionId);

    // Add a non-conformity so both endpoints have data to return.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/inspections/' . $inspectionId . '/non-conformities',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'description' => 'Missing tamper seal.',
        'severity' => 'medium',
      ]) ?: '',
    );

    $ncId = $this->extractResourceId(
      $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}'),
    );
    $this->assertNotNull($ncId);

    // GET the single non-conformity.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/inspections/' . $inspectionId . '/non-conformities/' . $ncId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $getNcResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $getNcResponse->getStatusCode(),
      'Getting a single non-conformity should return 200. Response: ' . $getNcResponse->getContent(),
    );

    $getNcData = $this->decodeJsonResponse($getNcResponse->getContent() ?: '{}');
    $this->assertSame($ncId, $this->extractResourceId($getNcData));
    $this->assertSame('medium', $getNcData['severity'] ?? null);
    $this->assertSame('open', $getNcData['status'] ?? null);

    // GET the organization-wide non-conformity list.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/non-conformities',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $listResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $listResponse->getStatusCode(),
      'Listing organization non-conformities should return 200. Response: ' . $listResponse->getContent(),
    );

    $listData = $this->decodeJsonResponse($listResponse->getContent() ?: '{}');
    $this->assertArrayHasKey('member', $listData, "Organization non-conformity list should expose a hydra 'member' collection.");
    $members = $this->getCollectionMembers($listData);
    $this->assertTrue(
      $this->collectionContainsId($members, $ncId),
      'The created non-conformity should appear in the organization-wide list.',
    );
  }

  /**
   * The canonical (non-legacy) inspection collection is scoped through the
   * `organization` query IRI and exercises the CanonicalInspectionProvider.
   */
  public function testCanonicalInspectionCollectionFlow(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'canonical-inspection-' . uniqid() . '@example.com';
    $password = 'Canonical123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Canonical Inspection Org ' . uniqid());
    $this->assertNotNull($organizationId);

    $client->request(
      method: 'GET',
      uri: '/api/inspections?organization=/api/organizations/' . $organizationId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Canonical inspection collection should return 200 for an authorized organization. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey('member', $data, "Canonical inspection collection should expose a hydra 'member' collection.");
    $this->assertTrue(is_array($data['member'] ?? null), "Canonical inspection collection 'member' should be an array.");
  }

  /**
   * Every uncovered Inspection Presentation endpoint must reject unauthenticated
   * requests (401/403) rather than expose data or 404. The catalog endpoints,
   * the canonical/response/attachment resources, and the facility-scoped list
   * are all asserted here.
   */
  public function testUncoveredInspectionEndpointsRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $orgId = '550e8400-e29b-41d4-a716-446655440000';
    $inspId = '550e8400-e29b-41d4-a716-446655440001';
    $facilityId = '550e8400-e29b-41d4-a716-446655440002';
    $ncId = '550e8400-e29b-41d4-a716-446655440003';
    $attachmentId = '550e8400-e29b-41d4-a716-446655440004';

    $endpoints = [
      // Legacy inspection extras.
      ['GET', '/api/organizations/' . $orgId . '/facilities/' . $facilityId . '/inspections'],
      ['PATCH', '/api/organizations/' . $orgId . '/inspections/' . $inspId],
      ['DELETE', '/api/organizations/' . $orgId . '/inspections/' . $inspId],
      // Non-conformity extras.
      ['GET', '/api/organizations/' . $orgId . '/non-conformities'],
      ['GET', '/api/organizations/' . $orgId . '/inspections/' . $inspId . '/non-conformities/' . $ncId],
      // Checklist update.
      ['PATCH', '/api/organizations/' . $orgId . '/checklists/' . $inspId],
      // Canonical inspection resource.
      ['GET', '/api/inspections'],
      ['POST', '/api/inspections'],
      ['GET', '/api/inspections/' . $inspId],
      ['PUT', '/api/inspections/' . $inspId],
      ['PATCH', '/api/inspections/' . $inspId],
      ['DELETE', '/api/inspections/' . $inspId],
      // Inspection response resource.
      ['GET', '/api/inspection-responses'],
      ['POST', '/api/inspection-responses'],
      ['GET', '/api/inspection-responses/' . $inspId],
      ['PUT', '/api/inspection-responses/' . $inspId],
      ['PATCH', '/api/inspection-responses/' . $inspId],
      ['DELETE', '/api/inspection-responses/' . $inspId],
      // Attachment resource.
      ['POST', '/api/inspections/' . $inspId . '/attachments'],
      ['GET', '/api/inspections/' . $inspId . '/attachments'],
      ['POST', '/api/non-conformities/' . $ncId . '/attachments'],
      ['GET', '/api/non-conformities/' . $ncId . '/attachments'],
      ['GET', '/api/inspection-attachments/' . $attachmentId],
      ['DELETE', '/api/inspection-attachments/' . $attachmentId],
    ];

    foreach ($endpoints as [$method, $uri]) {
      $client->request(
        method: $method,
        uri: $uri,
        server: ['HTTP_ACCEPT' => 'application/ld+json'],
        content: '{}',
      );

      $statusCode = $client->getResponse()->getStatusCode();

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

  private function createEquipment(KernelBrowser $client, string $token, string $organizationId): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/equipment',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'type' => 'fire_extinguisher',
        'brand' => 'Sicli',
        'model' => 'Pro 6',
        'serialNumber' => 'EXT-PRES-' . uniqid(),
        'locationLabel' => 'Floor 1 – Presentation',
      ]) ?: '',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');

    return $this->extractResourceId($data);
  }

  private function createDraftInspection(KernelBrowser $client, string $token, string $organizationId, string $equipmentId): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/inspections',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'equipmentId' => $equipmentId,
        'result' => 'pass',
        'performedAt' => '2026-03-01T10:00:00+00:00',
        'inspectorType' => 'external',
        'inspectorName' => 'Safety Corp Inspector',
      ]) ?: '',
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
