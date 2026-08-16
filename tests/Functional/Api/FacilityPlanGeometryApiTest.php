<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function base64_decode;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function sys_get_temp_dir;
use function tempnam;

/**
 * Test FacilityPlanGeometryApiTest.
 *
 * Facility plan Phase 4: `PUT .../facilities/{id}/plan-geometry` and
 * `GET .../facilities/{id}/plan-overlay`.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityPlanGeometryApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '660e8400-e29b-41d4-a716-446655470000';

  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655470001';

  private const string ADMIN_USER_ID = '660e8400-e29b-41d4-a716-446655470002';

  private const string ADMIN_MEMBER_ID = '660e8400-e29b-41d4-a716-446655470022';

  private const string PLAIN_MEMBER_USER_ID = '660e8400-e29b-41d4-a716-446655470003';

  private const string OUTSIDER_ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655470004';

  private const string OUTSIDER_USER_ID = '660e8400-e29b-41d4-a716-446655470005';

  private const string ROOT_FACILITY_ID = '660e8400-e29b-41d4-a716-446655470010';

  private const string CHILD_ZONE_ID = '660e8400-e29b-41d4-a716-446655470011';

  private const string UNRELATED_ZONE_ID = '660e8400-e29b-41d4-a716-446655470012';

  private const string OUTSIDER_FACILITY_ID = '660e8400-e29b-41d4-a716-446655470013';

  private const array VALID_POINTS = [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]];

  #[Test]
  public function testPutPlanGeometryRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PUT', '/api/organizations/' . self::DUMMY_UUID . '/facilities/' . self::DUMMY_UUID . '/plan-geometry');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'PUT .../plan-geometry endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated PUT, got ' . $statusCode);
  }

  #[Test]
  public function testGetPlanOverlayRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/facilities/' . self::DUMMY_UUID . '/plan-overlay');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET .../plan-overlay endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET, got ' . $statusCode);
  }

  #[Test]
  public function testPutPlanGeometrySetsThenClears(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $attachmentId = $this->uploadFloorPlan($client, self::ROOT_FACILITY_ID, 'root-plan.png');

    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::CHILD_ZONE_ID . '/plan-geometry',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => $attachmentId, 'points' => self::VALID_POINTS]),
    );

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Setting a valid geometry should succeed. Response: ' . $response->getContent());

    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertIsArray($decoded['planGeometry'] ?? null);
    self::assertSame($attachmentId, $decoded['planGeometry']['attachmentId'] ?? null);
    self::assertSame(self::VALID_POINTS, $decoded['planGeometry']['points'] ?? null);

    // Clear: both fields null.
    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');
    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::CHILD_ZONE_ID . '/plan-geometry',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => null, 'points' => null]),
    );

    $clearedResponse = $client->getResponse();
    self::assertSame(200, $clearedResponse->getStatusCode(), 'Clearing the geometry should succeed. Response: ' . $clearedResponse->getContent());
    $clearedDecoded = json_decode((string) $clearedResponse->getContent(), true);
    self::assertIsArray($clearedDecoded);
    // API Platform omits null fields entirely rather than serializing `null`.
    self::assertArrayNotHasKey('planGeometry', $clearedDecoded);
  }

  #[Test]
  public function testPutPlanGeometryOnAnAncestorAttachmentSucceeds(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    // Attached directly to the ROOT — CHILD_ZONE_ID is not the owner, only an ancestor.
    $attachmentId = $this->uploadFloorPlan($client, self::ROOT_FACILITY_ID, 'root-plan.png');

    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::CHILD_ZONE_ID . '/plan-geometry',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => $attachmentId, 'points' => self::VALID_POINTS]),
    );

    self::assertSame(200, $client->getResponse()->getStatusCode(), 'An ancestor-owned floor plan must be assignable. Response: ' . $client->getResponse()->getContent());
  }

  #[Test]
  public function testPutPlanGeometryRejectsFewerThanThreePointsWith422(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $attachmentId = $this->uploadFloorPlan($client, self::ROOT_FACILITY_ID, 'root-plan.png');

    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::CHILD_ZONE_ID . '/plan-geometry',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => $attachmentId, 'points' => [[0.1, 0.1], [0.4, 0.1]]]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), 'Fewer than 3 points must fail input validation. Response: ' . $client->getResponse()->getContent());
  }

  #[Test]
  public function testPutPlanGeometryRejectsAnOutOfBoundsCoordinateWith400(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $attachmentId = $this->uploadFloorPlan($client, self::ROOT_FACILITY_ID, 'root-plan.png');

    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::CHILD_ZONE_ID . '/plan-geometry',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => $attachmentId, 'points' => [[0.1, 0.1], [0.4, 0.1], [1.5, 0.4]]]),
    );

    self::assertSame(400, $client->getResponse()->getStatusCode(), 'An out-of-bounds coordinate must fail the domain invariant. Response: ' . $client->getResponse()->getContent());
  }

  #[Test]
  public function testPutPlanGeometryReturns404ForAnUnknownFacility(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::DUMMY_UUID . '/plan-geometry',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => null, 'points' => null]),
    );

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testPutPlanGeometryReturns404ForACallerFromAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::OUTSIDER_USER_ID, 'plan-geometry-outsider@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::CHILD_ZONE_ID . '/plan-geometry',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => null, 'points' => null]),
    );

    self::assertSame(404, $client->getResponse()->getStatusCode(), 'A caller outside the owning organization must get 404, not 403.');
  }

  #[Test]
  public function testPutPlanGeometryRejectsMemberWithoutWritePermissionWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'plan-geometry-plain-member@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::CHILD_ZONE_ID . '/plan-geometry',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => null, 'points' => null]),
    );

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testPutPlanGeometryRejectsADocumentAttachmentWith409(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $documentAttachmentId = $this->uploadDocument($client, self::ROOT_FACILITY_ID);

    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::CHILD_ZONE_ID . '/plan-geometry',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => $documentAttachmentId, 'points' => self::VALID_POINTS]),
    );

    self::assertSame(409, $client->getResponse()->getStatusCode(), 'A document attachment must be refused as a plan. Response: ' . $client->getResponse()->getContent());
  }

  #[Test]
  public function testPutPlanGeometryRejectsANonAncestorAttachmentWith409(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    // Attached to UNRELATED_ZONE_ID, which is neither CHILD_ZONE_ID nor one of its ancestors.
    $attachmentId = $this->uploadFloorPlan($client, self::UNRELATED_ZONE_ID, 'unrelated-plan.png');

    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::CHILD_ZONE_ID . '/plan-geometry',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => $attachmentId, 'points' => self::VALID_POINTS]),
    );

    self::assertSame(409, $client->getResponse()->getStatusCode(), 'A non-ancestor floor plan must be refused. Response: ' . $client->getResponse()->getContent());
  }

  #[Test]
  public function testGetPlanOverlayReturnsSelfAndDescendantZones(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $attachmentId = $this->uploadFloorPlan($client, self::ROOT_FACILITY_ID, 'root-plan.png');

    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::CHILD_ZONE_ID . '/plan-geometry',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => $attachmentId, 'points' => self::VALID_POINTS]),
    );
    self::assertSame(200, $client->getResponse()->getStatusCode());

    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::ROOT_FACILITY_ID . '/plan-overlay?attachmentId=' . $attachmentId);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Response: ' . $response->getContent());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame($attachmentId, $decoded['attachmentId'] ?? null);
    $zones = $decoded['zones'] ?? null;
    self::assertIsArray($zones);
    self::assertCount(1, $zones);
    $zone = $zones[0];
    self::assertIsArray($zone);
    self::assertSame(self::CHILD_ZONE_ID, $zone['facilityId'] ?? null);
    self::assertSame(self::VALID_POINTS, $zone['points'] ?? null);
  }

  #[Test]
  public function testGetPlanOverlayDefaultsToThePrimaryPlanWhenAttachmentIdOmitted(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $attachmentId = $this->uploadFloorPlan($client, self::ROOT_FACILITY_ID, 'root-plan.png');

    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');
    $client->request('POST', '/api/facility-attachments/' . $attachmentId . '/primary');
    self::assertSame(200, $client->getResponse()->getStatusCode(), 'Promoting to primary should succeed.');

    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');
    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::CHILD_ZONE_ID . '/plan-geometry',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => $attachmentId, 'points' => self::VALID_POINTS]),
    );
    self::assertSame(200, $client->getResponse()->getStatusCode());

    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::ROOT_FACILITY_ID . '/plan-overlay');

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Response: ' . $response->getContent());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame($attachmentId, $decoded['attachmentId'] ?? null);
  }

  #[Test]
  public function testGetPlanOverlayReturnsEmptyZonesWhenNothingIsBound(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $attachmentId = $this->uploadFloorPlan($client, self::ROOT_FACILITY_ID, 'root-plan.png');

    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::ROOT_FACILITY_ID . '/plan-overlay?attachmentId=' . $attachmentId);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame([], $decoded['zones'] ?? null);
  }

  #[Test]
  public function testGetPlanOverlayReturns404WhenNoPrimaryPlanAndNoExplicitAttachmentId(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-geometry-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::ROOT_FACILITY_ID . '/plan-overlay');

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testGetPlanOverlayReturns404ForACallerFromAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::OUTSIDER_USER_ID, 'plan-geometry-outsider@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::ROOT_FACILITY_ID . '/plan-overlay');

    self::assertSame(404, $client->getResponse()->getStatusCode(), 'A caller outside the owning organization must get 404, not 403.');
  }

  #[Test]
  public function testGetPlanOverlayRejectsMemberWithoutReadPermissionWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'plan-geometry-plain-member@example.com');

    // The read-only role in this suite deliberately omits organization.facilities.read too.
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::ROOT_FACILITY_ID . '/plan-overlay');

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  /**
   * Method uploadFloorPlan.
   */
  private function uploadFloorPlan(KernelBrowser $client, string $facilityId, string $fileName): string
  {
    $path = tempnam(sys_get_temp_dir(), 'facility-plan-geometry-');
    self::assertIsString($path);
    file_put_contents($path, $this->minimalPngBytes());

    $client->request(
      method: 'POST',
      uri: '/api/facilities/' . $facilityId . '/attachments',
      parameters: ['kind' => 'floor_plan'],
      files: ['file' => new UploadedFile(path: $path, originalName: $fileName, mimeType: 'image/png', test: true)],
    );

    $response = $client->getResponse();
    self::assertSame(201, $response->getStatusCode(), 'Floor plan upload should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertIsString($decoded['id']);

    return $decoded['id'];
  }

  /**
   * Method uploadDocument.
   */
  private function uploadDocument(KernelBrowser $client, string $facilityId): string
  {
    $path = tempnam(sys_get_temp_dir(), 'facility-plan-geometry-doc-');
    self::assertIsString($path);
    file_put_contents($path, '%PDF-1.4' . "\n" . '%%EOF');

    $client->request(
      method: 'POST',
      uri: '/api/facilities/' . $facilityId . '/attachments',
      files: ['file' => new UploadedFile(path: $path, originalName: 'doc.pdf', mimeType: 'application/pdf', test: true)],
    );

    $response = $client->getResponse();
    self::assertSame(201, $response->getStatusCode(), 'Document upload should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertIsString($decoded['id']);

    return $decoded['id'];
  }

  /**
   * A real 1x1 PNG. `MultipartAttachmentGuard` sniffs the real bytes.
   */
  private function minimalPngBytes(): string
  {
    $decoded = base64_decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
      true,
    );
    self::assertIsString($decoded);

    return $decoded;
  }

  /**
   * Method loginAs.
   *
   * Authenticates the client against the stateless `api` firewall (the token
   * is stored in the container, not the session).
   */
  private function loginAs(KernelBrowser $client, string $userId, string $email): void
  {
    $user = new SecurityUser(
      id: $userId,
      email: $email,
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');
  }

  /**
   * Method seedOrganization.
   */
  private function seedOrganization(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::ORGANIZATION_ID, self::OUTSIDER_ORGANIZATION_ID] as $organizationId) {
      $existing = $entityManager->find(OrganizationRecord::class, $organizationId);
      if ($existing instanceof OrganizationRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Plan Geometry Test Org';
    $organization->slug = 'plan-geometry-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Plan Geometry Outsider Org';
    $outsiderOrganization->slug = 'plan-geometry-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '660e8400-e29b-41d4-a716-446655470020';
    $adminRole->organization = $organization;
    $adminRole->name = 'plan-geometry-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '660e8400-e29b-41d4-a716-446655470021';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'plan-geometry-read-only';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without facilities.read/write access.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = '660e8400-e29b-41d4-a716-446655470024';
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'plan-geometry-outsider-full-access';
    $outsiderRole->permissions = ['*'];
    $outsiderRole->description = 'Functional-test-only role for the unrelated organization.';
    $outsiderRole->isSystem = false;
    $outsiderRole->createdAt = $now;
    $entityManager->persist($outsiderRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = self::ADMIN_MEMBER_ID;
    $adminMember->organization = $organization;
    $adminMember->userId = self::ADMIN_USER_ID;
    $adminMember->isActive = true;
    $adminMember->joinedAt = $now;
    $entityManager->persist($adminMember);

    $adminAssignment = new OrganizationMemberRoleRecord();
    $adminAssignment->member = $adminMember;
    $adminAssignment->role = $adminRole;
    $adminAssignment->assignedAt = $now;
    $entityManager->persist($adminAssignment);

    $plainMember = new OrganizationMemberRecord();
    $plainMember->id = '660e8400-e29b-41d4-a716-446655470023';
    $plainMember->organization = $organization;
    $plainMember->userId = self::PLAIN_MEMBER_USER_ID;
    $plainMember->isActive = true;
    $plainMember->joinedAt = $now;
    $entityManager->persist($plainMember);

    $plainAssignment = new OrganizationMemberRoleRecord();
    $plainAssignment->member = $plainMember;
    $plainAssignment->role = $readOnlyRole;
    $plainAssignment->assignedAt = $now;
    $entityManager->persist($plainAssignment);

    $outsiderMember = new OrganizationMemberRecord();
    $outsiderMember->id = '660e8400-e29b-41d4-a716-446655470025';
    $outsiderMember->organization = $outsiderOrganization;
    $outsiderMember->userId = self::OUTSIDER_USER_ID;
    $outsiderMember->isActive = true;
    $outsiderMember->joinedAt = $now;
    $entityManager->persist($outsiderMember);

    $outsiderAssignment = new OrganizationMemberRoleRecord();
    $outsiderAssignment->member = $outsiderMember;
    $outsiderAssignment->role = $outsiderRole;
    $outsiderAssignment->assignedAt = $now;
    $entityManager->persist($outsiderAssignment);

    $entityManager->flush();
  }

  /**
   * Method seedFacilities.
   *
   * Seeds (idempotently) a small hierarchy: ROOT (site) -> CHILD_ZONE (zone),
   * plus an UNRELATED_ZONE sibling of ROOT, and one facility in the outsider
   * organization.
   */
  private function seedFacilities(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::ROOT_FACILITY_ID, self::CHILD_ZONE_ID, self::UNRELATED_ZONE_ID, self::OUTSIDER_FACILITY_ID] as $facilityId) {
      $existing = $entityManager->find(FacilityRecord::class, $facilityId);
      if ($existing instanceof FacilityRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    /** @var OrganizationRecord $outsiderOrganization */
    $outsiderOrganization = $entityManager->getReference(OrganizationRecord::class, self::OUTSIDER_ORGANIZATION_ID);

    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $root = new FacilityRecord();
    $root->id = self::ROOT_FACILITY_ID;
    $root->organization = $organization;
    $root->type = 'site';
    $root->name = 'Plan Geometry Root Site';
    $root->status = 'active';
    $root->metadata = [];
    $root->createdAt = $now;
    $root->updatedAt = $now;
    $entityManager->persist($root);

    $child = new FacilityRecord();
    $child->id = self::CHILD_ZONE_ID;
    $child->organization = $organization;
    $child->parentFacility = $root;
    $child->type = 'zone';
    $child->name = 'Plan Geometry Child Zone';
    $child->status = 'active';
    $child->metadata = [];
    $child->createdAt = $now;
    $child->updatedAt = $now;
    $entityManager->persist($child);

    $unrelated = new FacilityRecord();
    $unrelated->id = self::UNRELATED_ZONE_ID;
    $unrelated->organization = $organization;
    $unrelated->type = 'zone';
    $unrelated->name = 'Plan Geometry Unrelated Zone';
    $unrelated->status = 'active';
    $unrelated->metadata = [];
    $unrelated->createdAt = $now;
    $unrelated->updatedAt = $now;
    $entityManager->persist($unrelated);

    $outsiderFacility = new FacilityRecord();
    $outsiderFacility->id = self::OUTSIDER_FACILITY_ID;
    $outsiderFacility->organization = $outsiderOrganization;
    $outsiderFacility->type = 'site';
    $outsiderFacility->name = 'Outsider Site';
    $outsiderFacility->status = 'active';
    $outsiderFacility->metadata = [];
    $outsiderFacility->createdAt = $now;
    $outsiderFacility->updatedAt = $now;
    $entityManager->persist($outsiderFacility);

    $entityManager->flush();
  }
}
