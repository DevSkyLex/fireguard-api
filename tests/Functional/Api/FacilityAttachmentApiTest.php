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
use function sys_get_temp_dir;
use function tempnam;

/**
 * Test FacilityAttachmentApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityAttachmentApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655490001';

  private const string ADMIN_USER_ID = '550e8400-e29b-41d4-a716-446655490002';

  private const string ADMIN_MEMBER_ID = '550e8400-e29b-41d4-a716-446655490022';

  private const string PLAIN_MEMBER_USER_ID = '550e8400-e29b-41d4-a716-446655490003';

  private const string OUTSIDER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655490004';

  private const string OUTSIDER_USER_ID = '550e8400-e29b-41d4-a716-446655490005';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655490010';

  #[Test]
  public function testUploadFacilityAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/facilities/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /facilities/{id}/attachments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /facilities/{id}/attachments, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListFacilityAttachmentsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/facilities/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /facilities/{id}/attachments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /facilities/{id}/attachments, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetFacilityAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/facility-attachments/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /facility-attachments/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /facility-attachments/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testDeleteFacilityAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/facility-attachments/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /facility-attachments/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /facility-attachments/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testSetPrimaryFacilityAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/facility-attachments/' . self::DUMMY_UUID . '/primary');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /facility-attachments/{id}/primary endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /facility-attachments/{id}/primary, got ' . $statusCode,
    );
  }

  #[Test]
  public function testUploadFloorPlanRoundTripsKindAndDimensions(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacility();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');

    $attachmentId = $this->uploadFloorPlan($client, 'ground-floor.png', $this->minimalPngBytes());

    static::ensureKernelShutdown();
    $getClient = static::createClient();
    $this->loginAs($getClient, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');
    $getClient->request('GET', '/api/facility-attachments/' . $attachmentId);

    $decoded = json_decode((string) $getClient->getResponse()->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame('floor_plan', $decoded['kind'] ?? null);
    self::assertFalse($decoded['isPrimaryPlan'] ?? null);
    self::assertSame(1, $decoded['imageWidth'] ?? null);
    self::assertSame(1, $decoded['imageHeight'] ?? null);
  }

  #[Test]
  public function testUploadFloorPlanWithADisallowedMimeTypeIsRejectedWith422(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacility();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');

    $path = tempnam(sys_get_temp_dir(), 'facility-floor-plan-');
    self::assertIsString($path);
    file_put_contents($path, "%PDF-1.4\n%%EOF");
    $uploadedFile = new UploadedFile(path: $path, originalName: 'plan.pdf', mimeType: 'application/pdf', test: true);

    $client->request(
      method: 'POST',
      uri: '/api/facilities/' . self::FACILITY_ID . '/attachments',
      parameters: ['kind' => 'floor_plan'],
      files: ['file' => $uploadedFile],
    );

    self::assertSame(
      expected: 422,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A PDF uploaded as a floor plan must be rejected with 422. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testUploadWithoutAKindDefaultsToDocument(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacility();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');

    $path = tempnam(sys_get_temp_dir(), 'facility-doc-');
    self::assertIsString($path);
    file_put_contents($path, "%PDF-1.4\n%%EOF");
    $uploadedFile = new UploadedFile(path: $path, originalName: 'report.pdf', mimeType: 'application/pdf', test: true);

    $client->request(method: 'POST', uri: '/api/facilities/' . self::FACILITY_ID . '/attachments', files: ['file' => $uploadedFile]);

    $decoded = json_decode((string) $client->getResponse()->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame('document', $decoded['kind'] ?? null);
  }

  #[Test]
  public function testListFacilityAttachmentsFiltersByKind(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacility();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');
    $this->uploadFloorPlan($client, 'plan.png', $this->minimalPngBytes());

    static::ensureKernelShutdown();
    $documentClient = static::createClient();
    $this->loginAs($documentClient, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');
    $path = tempnam(sys_get_temp_dir(), 'facility-doc-');
    self::assertIsString($path);
    file_put_contents($path, "%PDF-1.4\n%%EOF");
    $documentClient->request(
      method: 'POST',
      uri: '/api/facilities/' . self::FACILITY_ID . '/attachments',
      files: ['file' => new UploadedFile(path: $path, originalName: 'report.pdf', mimeType: 'application/pdf', test: true)],
    );
    self::assertSame(201, $documentClient->getResponse()->getStatusCode());

    static::ensureKernelShutdown();
    $listClient = static::createClient();
    $this->loginAs($listClient, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');
    $listClient->request('GET', '/api/facilities/' . self::FACILITY_ID . '/attachments?kind=floor_plan');

    $decoded = json_decode((string) $listClient->getResponse()->getContent(), true);
    self::assertIsArray($decoded);
    self::assertIsArray($decoded['member']);
    self::assertCount(1, $decoded['member']);
    self::assertIsArray($decoded['member'][0]);
    self::assertSame('floor_plan', $decoded['member'][0]['kind'] ?? null);
  }

  #[Test]
  public function testSetPrimaryPromotesAFloorPlanAndSwapsThePreviousOne(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacility();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');
    $firstId = $this->uploadFloorPlan($client, 'ground-floor.png', $this->minimalPngBytes());

    static::ensureKernelShutdown();
    $uploadSecondClient = static::createClient();
    $this->loginAs($uploadSecondClient, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');
    $secondId = $this->uploadFloorPlan($uploadSecondClient, 'first-floor.png', $this->minimalPngBytes());

    static::ensureKernelShutdown();
    $firstPrimaryClient = static::createClient();
    $this->loginAs($firstPrimaryClient, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');
    $firstPrimaryClient->request('POST', '/api/facility-attachments/' . $firstId . '/primary');
    self::assertSame(200, $firstPrimaryClient->getResponse()->getStatusCode());

    static::ensureKernelShutdown();
    $secondPrimaryClient = static::createClient();
    $this->loginAs($secondPrimaryClient, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');
    $secondPrimaryClient->request('POST', '/api/facility-attachments/' . $secondId . '/primary');
    $secondResponse = $secondPrimaryClient->getResponse();
    self::assertSame(200, $secondResponse->getStatusCode());
    $secondDecoded = json_decode((string) $secondResponse->getContent(), true);
    self::assertIsArray($secondDecoded);
    self::assertTrue($secondDecoded['isPrimaryPlan'] ?? null);

    static::ensureKernelShutdown();
    $getFirstClient = static::createClient();
    $this->loginAs($getFirstClient, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');
    $getFirstClient->request('GET', '/api/facility-attachments/' . $firstId);
    $firstDecoded = json_decode((string) $getFirstClient->getResponse()->getContent(), true);
    self::assertIsArray($firstDecoded);
    self::assertFalse($firstDecoded['isPrimaryPlan'] ?? null, 'The previous primary must be cleared by the swap.');
  }

  #[Test]
  public function testSetPrimaryRefusesADocumentAttachmentWith409(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacility();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');

    $path = tempnam(sys_get_temp_dir(), 'facility-doc-');
    self::assertIsString($path);
    file_put_contents($path, "%PDF-1.4\n%%EOF");
    $client->request(
      method: 'POST',
      uri: '/api/facilities/' . self::FACILITY_ID . '/attachments',
      files: ['file' => new UploadedFile(path: $path, originalName: 'report.pdf', mimeType: 'application/pdf', test: true)],
    );
    $decoded = json_decode((string) $client->getResponse()->getContent(), true);
    self::assertIsArray($decoded);
    self::assertIsString($decoded['id']);

    static::ensureKernelShutdown();
    $primaryClient = static::createClient();
    $this->loginAs($primaryClient, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');
    $primaryClient->request('POST', '/api/facility-attachments/' . $decoded['id'] . '/primary');

    self::assertSame(
      expected: 409,
      actual: $primaryClient->getResponse()->getStatusCode(),
      message: 'Setting a document attachment as primary must be rejected with 409.',
    );
  }

  #[Test]
  public function testSetPrimaryRejectsMemberWithoutWritePermissionWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacility();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');
    $attachmentId = $this->uploadFloorPlan($client, 'plan.png', $this->minimalPngBytes());

    static::ensureKernelShutdown();
    $memberClient = static::createClient();
    $this->loginAs($memberClient, self::PLAIN_MEMBER_USER_ID, 'facility-attachment-plain-member@example.com');
    $memberClient->request('POST', '/api/facility-attachments/' . $attachmentId . '/primary');

    self::assertSame(
      expected: 403,
      actual: $memberClient->getResponse()->getStatusCode(),
      message: 'A member without organization.facilities.write must get 403.',
    );
  }

  #[Test]
  public function testSetPrimaryReturns404ForACallerFromAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacility();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-attachment-admin@example.com');
    $attachmentId = $this->uploadFloorPlan($client, 'plan.png', $this->minimalPngBytes());

    static::ensureKernelShutdown();
    $outsiderClient = static::createClient();
    $this->loginAs($outsiderClient, self::OUTSIDER_USER_ID, 'facility-attachment-outsider@example.com');
    $outsiderClient->request('POST', '/api/facility-attachments/' . $attachmentId . '/primary');

    self::assertSame(
      expected: 404,
      actual: $outsiderClient->getResponse()->getStatusCode(),
      message: 'A caller outside the owning organization must get 404, not 403.',
    );
  }

  /**
   * Method uploadFloorPlan.
   *
   * Uploads a `kind=floor_plan` multipart file to {@see self::FACILITY_ID}
   * as the currently logged-in client and returns the created attachment id.
   */
  private function uploadFloorPlan(KernelBrowser $client, string $fileName, string $contents): string
  {
    $path = tempnam(sys_get_temp_dir(), 'facility-floor-plan-');
    self::assertIsString($path);
    file_put_contents($path, $contents);

    $client->request(
      method: 'POST',
      uri: '/api/facilities/' . self::FACILITY_ID . '/attachments',
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
   *
   * Seeds (idempotently) an organization with an admin member (permissions
   * `['*']`) and a plain member (`organization.read` only, no
   * `organization.facilities.write`), plus a second, unrelated organization
   * with its own member — the "outside scope" caller.
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

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Facility Attachment Test Org';
    $organization->slug = 'facility-attachment-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Facility Attachment Outsider Org';
    $outsiderOrganization->slug = 'facility-attachment-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '550e8400-e29b-41d4-a716-446655490020';
    $adminRole->organization = $organization;
    $adminRole->name = 'facility-attachment-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '550e8400-e29b-41d4-a716-446655490021';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'facility-attachment-read-only';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without facilities.write access.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = '550e8400-e29b-41d4-a716-446655490024';
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'facility-attachment-outsider-full-access';
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
    $plainMember->id = '550e8400-e29b-41d4-a716-446655490023';
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
    $outsiderMember->id = '550e8400-e29b-41d4-a716-446655490025';
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
   * Method seedFacility.
   *
   * Seeds (idempotently) a single active facility owned by
   * {@see self::ORGANIZATION_ID}.
   */
  private function seedFacility(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $existing = $entityManager->find(FacilityRecord::class, self::FACILITY_ID);
    if ($existing instanceof FacilityRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $facility->type = 'site';
    $facility->name = 'Facility Attachment Test Site';
    $facility->status = 'active';
    $facility->metadata = [];
    $facility->createdAt = $now;
    $facility->updatedAt = $now;
    $entityManager->persist($facility);
    $entityManager->flush();
  }
}
