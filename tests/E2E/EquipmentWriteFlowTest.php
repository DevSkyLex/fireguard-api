<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

use function file_exists;
use function file_put_contents;
use function is_array;
use function is_string;
use function json_encode;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

use const UPLOAD_ERR_OK;

/**
 * Test EquipmentWriteFlow.
 *
 * Presentation-layer write coverage for the Equipment module: exercises the
 * create / media / canonical-mutation processors and the media provider through
 * authenticated HTTP requests, plus one unauthenticated probe per route.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentWriteFlowTest extends OAuth2WebTestCase
{
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  private const string LD_JSON = 'application/ld+json';

  private const string MERGE_PATCH = 'application/merge-patch+json';

  /**
   * A random UUID used purely to probe route existence on unauthenticated
   * requests. Security is evaluated before the provider/processor runs, so a
   * non-existent identifier still yields 401/403 rather than 404.
   */
  private const string PLACEHOLDER_UUID = '550e8400-e29b-41d4-a716-446655440000';

  // #region CreateEquipmentProcessor (POST /api/organizations/{id}/equipment)

  #[Test]
  public function testCreateEquipmentViaOrganizationScopedEndpoint(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment',
      server: $this->authServer($token, self::LD_JSON),
      content: json_encode([
        'type' => 'fire_extinguisher',
        'brand' => 'Sicli',
        'model' => 'Pro 6',
        'serialNumber' => 'WRITE-EXT-' . uniqid(),
        'locationLabel' => 'Write flow - corridor',
      ]) ?: '',
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_CREATED,
      $response->getStatusCode(),
      'Equipment creation should return 201. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertArrayHasKey('id', $data, 'Create response should expose the new id.');
    self::assertSame('fire_extinguisher', $data['type'] ?? null, 'Returned type should match the payload.');
    self::assertSame('in_stock', $data['status'] ?? null, 'New equipment should start in_stock.');
  }

  // #endregion

  // #region MediaProcessor + MediaProvider (POST/GET/DELETE /api/media)

  #[Test]
  public function testMediaUploadThenGetThenDeleteLifecycle(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $equipmentId = $this->firstSeededEquipmentId($client, $token);
    self::assertNotNull($equipmentId, 'A seeded equipment item should be available.');

    // Upload (MediaProcessor::upload). The temp file must survive the request,
    // so the whole lifecycle runs inside the try and finally only cleans up.
    $tempFile = $this->createTempMediaFile();

    try {
      $client->request(
        method: 'POST',
        uri: '/api/media',
        parameters: [
          'equipment' => '/api/equipment/' . $equipmentId,
          'label' => 'Write flow media',
        ],
        files: [
          'file' => new UploadedFile(
            path: $tempFile,
            originalName: 'spec.pdf',
            mimeType: 'application/pdf',
            error: UPLOAD_ERR_OK,
            test: true,
          ),
        ],
        server: [
          'HTTP_ACCEPT' => self::LD_JSON,
          'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ],
      );

      $uploadResponse = $client->getResponse();
      self::assertSame(
        Response::HTTP_CREATED,
        $uploadResponse->getStatusCode(),
        'Media upload should return 201. Response: ' . $uploadResponse->getContent(),
      );

      $uploaded = $this->decodeJsonResponse($uploadResponse->getContent() ?: '{}');
      $mediaId = $uploaded['id'] ?? null;
      self::assertIsString($mediaId, 'Upload response should expose the media id.');
      self::assertSame('spec.pdf', $uploaded['fileName'] ?? null, 'Stored file name should match.');
      self::assertSame($equipmentId, $uploaded['equipmentId'] ?? null, 'Media should link back to the equipment.');
      $revision = $uploaded['revision'] ?? null;
      self::assertIsInt($revision, 'Upload response should expose a numeric revision.');

      // Fetch (MediaProvider::provide).
      $this->authenticatedGet($client, $token, '/api/media/' . $mediaId);

      $getResponse = $client->getResponse();
      self::assertSame(
        Response::HTTP_OK,
        $getResponse->getStatusCode(),
        'Media fetch should return 200. Response: ' . $getResponse->getContent(),
      );

      $fetched = $this->decodeJsonResponse($getResponse->getContent() ?: '{}');
      self::assertSame($mediaId, $fetched['id'] ?? null, 'Fetched media id should match the uploaded one.');

      // Delete (MediaProcessor::delete) — guarded by the optimistic If-Match revision.
      $client->request(
        method: 'DELETE',
        uri: '/api/media/' . $mediaId,
        server: $this->authServer($token, null, $revision),
      );

      self::assertSame(
        Response::HTTP_NO_CONTENT,
        $client->getResponse()->getStatusCode(),
        'Media delete should return 204. Response: ' . $client->getResponse()->getContent(),
      );
    } finally {
      if (file_exists($tempFile)) {
        unlink($tempFile);
      }
    }
  }

  // #endregion

  // #region CanonicalEquipmentMutationProcessor (PATCH/DELETE /api/equipment/{id})

  #[Test]
  public function testCanonicalEquipmentPatchThenDeleteLifecycle(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    // Create a fresh equipment through the canonical POST (CreateEquipmentProcessor,
    // organization-from-body branch) so the mutation flow stays self-contained.
    $client->request(
      method: 'POST',
      uri: '/api/equipment',
      server: $this->authServer($token, self::LD_JSON),
      content: json_encode([
        'organization' => '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID,
        'type' => 'smoke_detector',
        'serialNumber' => 'WRITE-CANON-' . uniqid(),
      ]) ?: '',
    );

    $createResponse = $client->getResponse();
    self::assertSame(
      Response::HTTP_CREATED,
      $createResponse->getStatusCode(),
      'Canonical equipment creation should return 201. Response: ' . $createResponse->getContent(),
    );

    $created = $this->decodeJsonResponse($createResponse->getContent() ?: '{}');
    $equipmentId = $created['id'] ?? null;
    self::assertIsString($equipmentId, 'Canonical create should expose the new id.');

    // Read the live revision required by the optimistic-concurrency guard.
    $this->authenticatedGet($client, $token, '/api/equipment/' . $equipmentId);
    $current = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $revision = $current['revision'] ?? null;
    self::assertIsInt($revision, 'Canonical GET should expose a numeric revision.');

    // Patch a free-form field (no status change → no facility requirement).
    $patchedBrand = 'Patched-' . uniqid();
    $client->request(
      method: 'PATCH',
      uri: '/api/equipment/' . $equipmentId,
      server: $this->authServer($token, self::MERGE_PATCH, $revision),
      content: json_encode(['brand' => $patchedBrand]) ?: '',
    );

    $patchResponse = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $patchResponse->getStatusCode(),
      'Canonical equipment patch should return 200. Response: ' . $patchResponse->getContent(),
    );

    $patched = $this->decodeJsonResponse($patchResponse->getContent() ?: '{}');
    self::assertSame($patchedBrand, $patched['brand'] ?? null, 'Patched brand should be persisted.');
    $nextRevision = $patched['revision'] ?? null;
    self::assertIsInt($nextRevision, 'Patch response should expose the incremented revision.');

    // Delete a published record decommissions it (canonical DELETE contract).
    $client->request(
      method: 'DELETE',
      uri: '/api/equipment/' . $equipmentId,
      server: $this->authServer($token, null, $nextRevision),
    );

    self::assertSame(
      Response::HTTP_NO_CONTENT,
      $client->getResponse()->getStatusCode(),
      'Canonical equipment delete should return 204. Response: ' . $client->getResponse()->getContent(),
    );

    // A published record is decommissioned (not hard-deleted) and stays readable.
    $this->authenticatedGet($client, $token, '/api/equipment/' . $equipmentId);
    self::assertSame(
      Response::HTTP_OK,
      $client->getResponse()->getStatusCode(),
      'Decommissioned equipment should remain readable. Response: ' . $client->getResponse()->getContent(),
    );
    $afterDelete = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame('decommissioned', $afterDelete['status'] ?? null, 'Published DELETE should decommission the asset.');
  }

  // #endregion

  // #region Route existence — one unauthenticated probe per driven route

  #[Test]
  public function testWriteRoutesRejectUnauthenticatedRequests(): void
  {
    $client = static::createClientWithFixtures();

    // CreateEquipmentProcessor — organization-scoped POST.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment',
      server: [
        'CONTENT_TYPE' => self::LD_JSON,
        'HTTP_ACCEPT' => self::LD_JSON,
      ],
      content: '{"type":"fire_extinguisher"}',
    );
    $this->assertRouteExistsButGuarded($client->getResponse());

    // CreateEquipmentProcessor — canonical POST.
    $client->request(
      method: 'POST',
      uri: '/api/equipment',
      server: [
        'CONTENT_TYPE' => self::LD_JSON,
        'HTTP_ACCEPT' => self::LD_JSON,
      ],
      content: '{"type":"fire_extinguisher"}',
    );
    $this->assertRouteExistsButGuarded($client->getResponse());

    // MediaProcessor — upload.
    $client->request(
      method: 'POST',
      uri: '/api/media',
      server: ['HTTP_ACCEPT' => self::LD_JSON],
    );
    $this->assertRouteExistsButGuarded($client->getResponse());

    // MediaProvider — fetch.
    $client->request(
      method: 'GET',
      uri: '/api/media/' . self::PLACEHOLDER_UUID,
      server: ['HTTP_ACCEPT' => self::LD_JSON],
    );
    $this->assertRouteExistsButGuarded($client->getResponse());

    // MediaProcessor — delete.
    $client->request(
      method: 'DELETE',
      uri: '/api/media/' . self::PLACEHOLDER_UUID,
      server: ['HTTP_ACCEPT' => self::LD_JSON],
    );
    $this->assertRouteExistsButGuarded($client->getResponse());

    // CanonicalEquipmentMutationProcessor — patch.
    $client->request(
      method: 'PATCH',
      uri: '/api/equipment/' . self::PLACEHOLDER_UUID,
      server: [
        'CONTENT_TYPE' => self::MERGE_PATCH,
        'HTTP_ACCEPT' => self::LD_JSON,
      ],
      content: '{"brand":"x"}',
    );
    $this->assertRouteExistsButGuarded($client->getResponse());

    // CanonicalEquipmentMutationProcessor — delete.
    $client->request(
      method: 'DELETE',
      uri: '/api/equipment/' . self::PLACEHOLDER_UUID,
      server: ['HTTP_ACCEPT' => self::LD_JSON],
    );
    $this->assertRouteExistsButGuarded($client->getResponse());
  }

  // #endregion

  // #region Helpers

  /**
   * Build a server header array for an authenticated request.
   *
   * @return array<string, string>
   */
  private function authServer(string $token, ?string $contentType = null, ?int $ifMatch = null): array
  {
    $server = [
      'HTTP_ACCEPT' => self::LD_JSON,
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];
    if (null !== $contentType) {
      $server['CONTENT_TYPE'] = $contentType;
    }
    if (null !== $ifMatch) {
      $server['HTTP_IF_MATCH'] = '"revision-' . $ifMatch . '"';
    }

    return $server;
  }

  private function authenticatedGet(KernelBrowser $client, string $token, string $uri): void
  {
    $client->request(
      method: 'GET',
      uri: $uri,
      server: [
        'HTTP_ACCEPT' => self::LD_JSON,
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );
  }

  private function createTempMediaFile(): string
  {
    $path = tempnam(sys_get_temp_dir(), 'equipment_media_');
    self::assertIsString($path, 'A temporary upload file should be created.');
    file_put_contents($path, "%PDF-1.4 fireguard write-flow media\n");

    return $path;
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
        'CONTENT_TYPE' => self::LD_JSON,
        'HTTP_ACCEPT' => self::LD_JSON,
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
