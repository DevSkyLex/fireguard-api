<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Shared\Infrastructure\DataFixtures\SeedUuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use User\Infrastructure\DataFixtures\UserFixtures;

use function hash;
use function is_array;
use function is_int;
use function is_string;
use function json_encode;
use function str_starts_with;

/**
 * Test ComplianceSnapshotFlow.
 *
 * Drives the archived safety register snapshot lifecycle end to end against
 * the seeded databases: the MAX-plan seeded organization's admin archives
 * the register (201 + metadata), lists the archive, downloads the stored
 * PDF and verifies its SHA-256 against the recorded `contentHash`; the
 * FREE-plan "Groupe Vigilance" owner is refused with the distinct
 * not-entitled 403; and the admin probing another organization's snapshot
 * surface answers 404, never confirming existence.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ComplianceSnapshotFlowTest extends OAuth2WebTestCase
{
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  // FREE-plan secondary organization owner (see UserFixtures::SECONDARY_ORG_OWNER_SEEDS).
  private const string VIGILANCE_OWNER_EMAIL = 'pierre.lambert@groupevigilance.example';

  private const string VIGILANCE_OWNER_PASSWORD = UserFixtures::STAFF_PASSWORD;

  // Seeded "Paris Headquarters" site belonging to the seeded organization.
  private const string SEEDED_FACILITY_ID = '22222222-2222-4222-8222-222222222221';

  /**
   * POST → 201, list, download — the full archive lifecycle, hash verified.
   */
  public function testEntitledAdminArchivesListsAndDownloadsTheRegisterSnapshot(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
    $base = '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/compliance/register-snapshots';

    // 1. Archive the organization-wide register.
    $client->request(
      method: 'POST',
      uri: $base,
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_CREATED,
      $response->getStatusCode(),
      'Entitled admin should archive the register snapshot. Response: ' . $response->getContent(),
    );

    $created = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $snapshotId = $created['id'] ?? null;
    $createdContentHash = $created['contentHash'] ?? null;
    $this->assertTrue(is_string($createdContentHash) && '' !== $createdContentHash, 'Creation response should carry the contentHash.');
    $this->assertTrue(is_string($snapshotId) && '' !== $snapshotId, 'Creation response should carry the snapshot id.');
    $this->assertSame(OrganizationFixtures::ORGANIZATION_ID, $created['organizationId'] ?? null);
    $this->assertNull($created['facilityId'] ?? null, 'Organization-wide snapshot should carry a null facilityId.');
    $this->assertSame('organization', $created['scope'] ?? null);
    $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $createdContentHash, 'contentHash should be a SHA-256 hex digest.');
    $sizeBytes = $created['sizeBytes'] ?? null;
    $this->assertTrue(is_int($sizeBytes) && $sizeBytes > 0, 'sizeBytes should reflect the stored PDF.');
    $this->assertTrue(is_string($created['generatedAt'] ?? null) && '' !== $created['generatedAt'], 'generatedAt should be carried.');

    // 2. The archive lists it, newest first.
    $client->request(
      method: 'GET',
      uri: $base,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $listResponse = $client->getResponse();
    $this->assertSame(Response::HTTP_OK, $listResponse->getStatusCode(), 'Snapshot list should answer 200. Response: ' . $listResponse->getContent());

    $list = $this->decodeJsonResponse($listResponse->getContent() ?: '{}');
    $members = $list['hydra:member'] ?? $list['member'] ?? null;
    $this->assertTrue(is_array($members) && [] !== $members, 'Snapshot list should carry at least the snapshot just created.');
    $first = $members[0];
    $this->assertTrue(is_array($first), 'Snapshot list members should be objects.');
    $this->assertSame($snapshotId, $first['id'] ?? null, 'The just-created snapshot should be the newest, listed first.');

    // 3. Download the archived bytes and verify integrity against the hash.
    $client->request(
      method: 'GET',
      uri: $base . '/' . $snapshotId . '/download',
      server: [
        'HTTP_ACCEPT' => 'application/pdf',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $downloadResponse = $client->getResponse();
    $this->assertSame(Response::HTTP_OK, $downloadResponse->getStatusCode(), 'Snapshot download should answer 200. Response: ' . $downloadResponse->getContent());

    $body = $downloadResponse->getContent();
    $this->assertTrue(is_string($body) && str_starts_with($body, '%PDF'), 'Archived snapshot body should be a PDF document.');
    $this->assertSame(
      $createdContentHash,
      hash('sha256', (string) $body),
      'Downloaded bytes must hash to the recorded contentHash — the integrity proof the archive exists for.',
    );
  }

  /**
   * The facility-scoped register archives with scope=facility.
   */
  public function testEntitledAdminArchivesAFacilityScopedSnapshot(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/compliance/register-snapshots',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['facilityId' => self::SEEDED_FACILITY_ID]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_CREATED,
      $response->getStatusCode(),
      'Facility-scoped snapshot should be created. Response: ' . $response->getContent(),
    );

    $created = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertSame('facility', $created['scope'] ?? null);
    $this->assertSame(self::SEEDED_FACILITY_ID, $created['facilityId'] ?? null);
  }

  /**
   * FREE-plan organization → the distinct not-entitled 403, even for its owner.
   */
  public function testFreePlanOwnerIsRefusedWithTheNotEntitledForbidden(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::VIGILANCE_OWNER_EMAIL, self::VIGILANCE_OWNER_PASSWORD);
    $vigilanceOrganizationId = SeedUuid::from('organization-seed-vigilance');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $vigilanceOrganizationId . '/compliance/register-snapshots',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $this->assertSame(
      Response::HTTP_FORBIDDEN,
      $client->getResponse()->getStatusCode(),
      'A FREE-plan organization must be refused snapshot creation with 403 (not entitled). Response: ' . $client->getResponse()->getContent(),
    );
  }

  /**
   * Cross-organization probing answers 404 on every snapshot operation.
   */
  public function testAnotherOrganizationsSnapshotSurfaceAnswersNotFound(): void
  {
    $client = static::createClientWithFixtures();
    // The seeded admin is NOT a member of the Vigilance organization.
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
    $vigilanceOrganizationId = SeedUuid::from('organization-seed-vigilance');
    $base = '/api/organizations/' . $vigilanceOrganizationId . '/compliance/register-snapshots';

    $client->request(
      method: 'POST',
      uri: $base,
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );
    $this->assertSame(
      Response::HTTP_NOT_FOUND,
      $client->getResponse()->getStatusCode(),
      'Creating a snapshot in a foreign organization must answer 404, exactly like an unknown organization id.',
    );

    $client->request(
      method: 'GET',
      uri: $base,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );
    $this->assertSame(
      Response::HTTP_NOT_FOUND,
      $client->getResponse()->getStatusCode(),
      'Listing a foreign organization\'s snapshots must answer 404.',
    );

    $client->request(
      method: 'GET',
      uri: $base . '/550e8400-e29b-41d4-a716-446655440000/download',
      server: [
        'HTTP_ACCEPT' => 'application/pdf',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );
    $this->assertSame(
      Response::HTTP_NOT_FOUND,
      $client->getResponse()->getStatusCode(),
      'Downloading from a foreign organization must answer 404, never confirming a snapshot exists.',
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
      'Seeded user login should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    $this->assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }

  // #endregion
}
