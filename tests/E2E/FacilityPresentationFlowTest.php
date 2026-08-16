<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function basename;
use function is_array;
use function is_string;
use function json_encode;
use function rawurlencode;
use function str_contains;
use function uniqid;

/**
 * Test FacilityPresentation.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityPresentationFlowTest extends OAuth2WebTestCase
{
  /**
   * Tree navigation: children and descendants of a facility.
   *
   * Covers LIST_FACILITY_CHILDREN and LIST_FACILITY_DESCENDANTS — authenticated
   * happy path over a two-level hierarchy (site -> building).
   */
  public function testFacilityChildrenAndDescendants(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticatedOwnerToken($client, 'facility-tree');
    $organizationId = $this->createOrganization($client, $token, 'Facility Tree Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $parentId = $this->createFacility($client, $token, $organizationId, ['type' => 'site', 'name' => 'Root Site']);
    $this->assertNotNull($parentId, 'Parent facility should be created.');

    $childId = $this->createFacility($client, $token, $organizationId, [
      'type' => 'building',
      'name' => 'Child Building',
      'parentFacilityId' => $parentId,
    ]);
    $this->assertNotNull($childId, 'Child facility should be created.');

    // Direct children of the parent.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/facilities/' . $parentId . '/children',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $childrenResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $childrenResponse->getStatusCode(),
      'Listing facility children should succeed. Response: ' . $childrenResponse->getContent(),
    );
    $children = $this->getCollectionMembers($this->decodeJsonResponse($childrenResponse->getContent() ?: '{}'));
    $this->assertTrue(
      $this->collectionContainsId($children, $childId),
      'Child facility should appear under the parent children.',
    );

    // All descendants of the parent.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/facilities/' . $parentId . '/descendants',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $descendantsResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $descendantsResponse->getStatusCode(),
      'Listing facility descendants should succeed. Response: ' . $descendantsResponse->getContent(),
    );
    $descendants = $this->getCollectionMembers($this->decodeJsonResponse($descendantsResponse->getContent() ?: '{}'));
    $this->assertTrue(
      $this->collectionContainsId($descendants, $childId),
      'Child facility should appear among the parent descendants.',
    );
  }

  /**
   * Canonical facility endpoints (top-level /facilities, organization-scoped).
   *
   * Covers CanonicalFacilityResource GetCollection (GET /facilities?organization=)
   * and Get (GET /facilities/{id}) — authenticated happy path.
   */
  public function testCanonicalFacilityReadEndpoints(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticatedOwnerToken($client, 'facility-canonical');
    $organizationId = $this->createOrganization($client, $token, 'Facility Canonical Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $facilityId = $this->createFacility($client, $token, $organizationId, ['type' => 'site', 'name' => 'Canonical Site']);
    $this->assertNotNull($facilityId, 'Facility should be created.');

    // Canonical collection scoped by organization IRI.
    $client->request(
      method: 'GET',
      uri: '/api/facilities?organization=' . rawurlencode('/api/organizations/' . $organizationId),
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $collectionResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $collectionResponse->getStatusCode(),
      'Listing canonical facilities should succeed. Response: ' . $collectionResponse->getContent(),
    );
    $members = $this->getCollectionMembers($this->decodeJsonResponse($collectionResponse->getContent() ?: '{}'));
    $this->assertTrue(
      $this->collectionContainsId($members, $facilityId),
      'Created facility should appear in the canonical collection.',
    );

    // Canonical item.
    $client->request(
      method: 'GET',
      uri: '/api/facilities/' . $facilityId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $itemResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $itemResponse->getStatusCode(),
      'Getting a canonical facility should succeed. Response: ' . $itemResponse->getContent(),
    );
    $itemData = $this->decodeJsonResponse($itemResponse->getContent() ?: '{}');
    $this->assertSame($facilityId, $this->extractResourceId($itemData), 'Canonical facility ID should match.');
    $this->assertArrayHasKey('type', $itemData, 'Canonical facility should expose its type.');
    $this->assertArrayHasKey('path', $itemData, 'Canonical facility should expose the ancestor breadcrumb.');
    $this->assertSame([], $itemData['path'], 'A root facility has an empty ancestor breadcrumb.');

    // A child facility's item read exposes its ancestor as the breadcrumb.
    $childId = $this->createFacility($client, $token, $organizationId, [
      'type' => 'building',
      'name' => 'Canonical Child',
      'parentFacilityId' => $facilityId,
    ]);
    $this->assertNotNull($childId, 'Child facility should be created.');

    $client->request(
      method: 'GET',
      uri: '/api/facilities/' . $childId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $childResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $childResponse->getStatusCode(),
      'Getting the child canonical facility should succeed. Response: ' . $childResponse->getContent(),
    );
    $childData = $this->decodeJsonResponse($childResponse->getContent() ?: '{}');
    $this->assertArrayHasKey('path', $childData, 'Child canonical facility should expose the ancestor breadcrumb.');
    $path = $childData['path'];
    $this->assertIsArray($path, 'The ancestor breadcrumb should be an array.');
    $this->assertCount(1, $path, 'The child has exactly one ancestor.');
    $ancestor = $path[0];
    $this->assertIsArray($ancestor, 'Each breadcrumb entry should be an array.');
    $this->assertSame($facilityId, $ancestor['id'] ?? null, 'The breadcrumb entry should reference the parent.');
    $this->assertSame('Canonical Site', $ancestor['name'] ?? null, 'The breadcrumb entry should carry the parent name.');
    $this->assertSame('site', $ancestor['type'] ?? null, 'The breadcrumb entry should carry the parent type.');
  }

  /**
   * Facility attachments listing.
   *
   * Covers FacilityAttachmentResource GetCollection
   * (GET /facilities/{facilityId}/attachments) — authenticated happy path over
   * a freshly created facility (empty attachment set). Upload (multipart) and
   * item Get/Delete are skipped: they need a stored binary the fixtures do not
   * provide.
   */
  public function testFacilityAttachmentsListing(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticatedOwnerToken($client, 'facility-attachments');
    $organizationId = $this->createOrganization($client, $token, 'Facility Attachments Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $facilityId = $this->createFacility($client, $token, $organizationId, ['type' => 'site', 'name' => 'Attach Site']);
    $this->assertNotNull($facilityId, 'Facility should be created.');

    $client->request(
      method: 'GET',
      uri: '/api/facilities/' . $facilityId . '/attachments',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Listing facility attachments should succeed. Response: ' . $response->getContent(),
    );
    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey('member', $data, 'Attachments response should be a Hydra collection.');
    $this->assertIsArray($data['member'], 'Attachments member should be an array.');
  }

  /**
   * Every uncovered Presentation route rejects unauthenticated access.
   *
   * The auth guard assertion is the reliable one across all Facility routes:
   * each route must exist (status != 404) and be guarded (401 or 403).
   */
  public function testFacilityEndpointsRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $organizationId = '550e8400-e29b-41d4-a716-446655440000';
    $facilityId = '550e8400-e29b-41d4-a716-446655440001';
    $attachmentId = '550e8400-e29b-41d4-a716-446655440002';

    $cases = [
      ['GET', '/api/facilities/statuses'],
      ['GET', '/api/facilities/types'],
      ['GET', '/api/organizations/' . $organizationId . '/facilities/' . $facilityId . '/children'],
      ['GET', '/api/organizations/' . $organizationId . '/facilities/' . $facilityId . '/descendants'],
      ['POST', '/api/organizations/' . $organizationId . '/facilities/' . $facilityId . '/archive'],
      ['PATCH', '/api/organizations/' . $organizationId . '/facilities/' . $facilityId . '/restore'],
      ['POST', '/api/organizations/' . $organizationId . '/facilities/' . $facilityId . '/move'],
      ['GET', '/api/facilities?organization=' . rawurlencode('/api/organizations/' . $organizationId)],
      ['GET', '/api/facilities/' . $facilityId],
      ['GET', '/api/facilities/' . $facilityId . '/attachments'],
      ['GET', '/api/facility-attachments/' . $attachmentId],
    ];

    foreach ($cases as [$method, $uri]) {
      $client->request(
        method: $method,
        uri: $uri,
        server: [
          'CONTENT_TYPE' => 'application/ld+json',
          'HTTP_ACCEPT' => 'application/ld+json',
        ],
        content: 'GET' === $method ? null : (json_encode([]) ?: ''),
      );

      $status = $client->getResponse()->getStatusCode();
      $this->assertNotSame(
        Response::HTTP_NOT_FOUND,
        $status,
        $method . ' ' . $uri . ' should exist (not 404). Got ' . $status,
      );
      $this->assertContains(
        $status,
        [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
        $method . ' ' . $uri . ' should be guarded (401/403). Got ' . $status,
      );
    }
  }

  // #region Helpers

  private function authenticatedOwnerToken(KernelBrowser $client, string $slug): string
  {
    $email = $slug . '-owner-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $email, $password);

    return $this->loginAndGetUserAccessToken($client, $email, $password);
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
   * @param array<string, mixed> $payload
   */
  private function createFacility(KernelBrowser $client, string $token, string $organizationId, array $payload): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facilities',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode($payload) ?: '',
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
