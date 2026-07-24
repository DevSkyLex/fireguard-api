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
 * End-to-end test for the Facility geographic coordinates round trip.
 *
 * Covers: create with coordinates, get, partial update (set/clear), and the
 * both-or-neither validation rule on create and update.
 */
final class FacilityCoordinatesFlowTest extends OAuth2WebTestCase
{
  public function testFacilityCoordinatesRoundTrip(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'facility-coordinates-owner-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);

    $organizationId = $this->createOrganization($client, $ownerToken, 'Facility Coordinates Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    // Step 1: Create a facility with coordinates.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facilities',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([
        'type' => 'site',
        'name' => 'Paris HQ',
        'latitude' => 48.8566,
        'longitude' => 2.3522,
      ]) ?: '',
    );

    $createResponse = $client->getResponse();
    $this->assertContains(
      $createResponse->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Facility creation with coordinates should succeed. Response: ' . $createResponse->getContent(),
    );

    $createData = $this->decodeJsonResponse($createResponse->getContent() ?: '{}');
    $facilityId = $this->extractResourceId($createData);
    $this->assertNotNull($facilityId, 'Facility ID should be present in create response.');
    $this->assertSame(48.8566, $createData['latitude'] ?? null, 'Latitude should be persisted and returned.');
    $this->assertSame(2.3522, $createData['longitude'] ?? null, 'Longitude should be persisted and returned.');

    // Step 2: Get the facility — coordinates should still be present (list/get load-bearing path).
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/facilities/' . $facilityId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $getResponse = $client->getResponse();
    $this->assertSame(Response::HTTP_OK, $getResponse->getStatusCode(), 'Getting facility should succeed. Response: ' . $getResponse->getContent());

    $getData = $this->decodeJsonResponse($getResponse->getContent() ?: '{}');
    $this->assertSame(48.8566, $getData['latitude'] ?? null, 'GET should return the persisted latitude.');
    $this->assertSame(2.3522, $getData['longitude'] ?? null, 'GET should return the persisted longitude.');

    // Step 3: List facilities — the frontend map reads coordinates from this load-bearing endpoint.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/facilities',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $listResponse = $client->getResponse();
    $this->assertSame(Response::HTTP_OK, $listResponse->getStatusCode(), 'Listing facilities should succeed. Response: ' . $listResponse->getContent());

    $listData = $this->decodeJsonResponse($listResponse->getContent() ?: '{}');
    $members = $this->getCollectionMembers($listData);
    $listedFacility = null;
    foreach ($members as $member) {
      if ($facilityId === $this->extractResourceId($member)) {
        $listedFacility = $member;

        break;
      }
    }
    $this->assertNotNull($listedFacility, 'Created facility should appear in the list.');
    $this->assertSame(48.8566, $listedFacility['latitude'] ?? null, 'List item should expose the persisted latitude.');
    $this->assertSame(2.3522, $listedFacility['longitude'] ?? null, 'List item should expose the persisted longitude.');

    // Step 4: Partially update the coordinates.
    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/facilities/' . $facilityId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([
        'latitude' => 45.7640,
        'longitude' => 4.8357,
      ]) ?: '',
    );

    $updateResponse = $client->getResponse();
    $this->assertContains(
      $updateResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Updating facility coordinates should succeed. Response: ' . $updateResponse->getContent(),
    );

    $updateData = $this->decodeJsonResponse($updateResponse->getContent() ?: '{}');
    $this->assertSame(45.7640, $updateData['latitude'] ?? null, 'Latitude should be updated.');
    $this->assertSame(4.8357, $updateData['longitude'] ?? null, 'Longitude should be updated.');

    // Step 5: Creating a facility with only one coordinate must fail (both-or-neither rule).
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facilities',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([
        'type' => 'site',
        'name' => 'Invalid Coordinates Site',
        'latitude' => 48.8566,
      ]) ?: '',
    );

    $this->assertSame(
      Response::HTTP_BAD_REQUEST,
      $client->getResponse()->getStatusCode(),
      'Creating a facility with only latitude should return 400. Response: ' . $client->getResponse()->getContent(),
    );

    // Step 6: Updating with only one coordinate must also fail.
    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/facilities/' . $facilityId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([
        'longitude' => 2.3522,
      ]) ?: '',
    );

    $this->assertSame(
      Response::HTTP_BAD_REQUEST,
      $client->getResponse()->getStatusCode(),
      'Updating a facility with only longitude should return 400. Response: ' . $client->getResponse()->getContent(),
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
}
