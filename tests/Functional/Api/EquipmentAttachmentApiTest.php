<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function base64_decode;
use function base64_encode;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;

/**
 * Test EquipmentAttachmentApiTest.
 *
 * Covers the two Equipment attachment upload paths — the base64 JSON
 * `POST .../equipment/{id}/attachments` (`AddAttachmentInput`,
 * `AddAttachmentProcessor`) and the canonical multipart `POST /api/media`
 * (`MediaProcessor`) — including the MIME/size policy both now enforce
 * through `Shared\Domain\Attachment\AttachmentConstraints`, closing the gap
 * self-flagged in `src/Equipment/MODULE.md`.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentAttachmentApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '770e8400-e29b-41d4-a716-446655480000';

  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655480001';

  private const string ADMIN_USER_ID = '770e8400-e29b-41d4-a716-446655480002';

  private const string ADMIN_MEMBER_ID = '770e8400-e29b-41d4-a716-446655480022';

  private const string PLAIN_MEMBER_USER_ID = '770e8400-e29b-41d4-a716-446655480003';

  private const string OUTSIDER_ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655480004';

  private const string OUTSIDER_USER_ID = '770e8400-e29b-41d4-a716-446655480005';

  private const string EQUIPMENT_ID = '770e8400-e29b-41d4-a716-446655480030';

  private const string OUTSIDER_EQUIPMENT_ID = '770e8400-e29b-41d4-a716-446655480031';

  #[Test]
  public function testAddAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/equipment/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST .../equipment/{id}/attachments endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST, got ' . $statusCode);
  }

  #[Test]
  public function testAddAttachmentAcceptsAnAllowedMimeTypeAndSize(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-attachment-admin@example.com');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::EQUIPMENT_ID . '/attachments',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode([
        'fileName' => 'inspection.pdf',
        'content' => base64_encode('%PDF-1.4 not a real pdf but well under the size cap'),
        'mimeType' => 'application/pdf',
        'label' => 'Inspection report',
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(201, $response->getStatusCode(), 'An allowed MIME type under the size cap must succeed. Response: ' . $response->getContent());

    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame('inspection.pdf', $decoded['fileName'] ?? null);
    self::assertSame('application/pdf', $decoded['mimeType'] ?? null);
  }

  #[Test]
  public function testAddAttachmentRejectsADisallowedMimeTypeWith422(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-attachment-admin@example.com');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::EQUIPMENT_ID . '/attachments',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode([
        'fileName' => 'payload.exe',
        'content' => base64_encode('MZ'),
        'mimeType' => 'application/x-msdownload',
      ]),
    );

    self::assertSame(
      expected: 422,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A disallowed MIME type must be rejected with 422. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testAddAttachmentRejectsAnOversizedPayloadWith422(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-attachment-admin@example.com');

    // AttachmentConstraints::MAX_SIZE_BYTES is 10 MiB; one byte over is
    // rejected before persistence. Unlike a real multipart upload, this
    // JSON/base64 body carries no php.ini upload_max_filesize ceiling to hit
    // first, so the 422 is reachable through the HTTP layer here.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::EQUIPMENT_ID . '/attachments',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode([
        'fileName' => 'huge.pdf',
        'content' => base64_encode(str_repeat('a', 10 * 1024 * 1024 + 1)),
        'mimeType' => 'application/pdf',
      ]),
    );

    self::assertSame(
      expected: 422,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A payload over the 10 MiB cap must be rejected with 422. Response status: ' . $client->getResponse()->getStatusCode(),
    );
  }

  #[Test]
  public function testAddAttachmentRejectsMemberWithoutWritePermissionWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'equipment-attachment-plain-member@example.com');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::EQUIPMENT_ID . '/attachments',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode([
        'fileName' => 'report.pdf',
        'content' => base64_encode('PDF content'),
        'mimeType' => 'application/pdf',
      ]),
    );

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testAddAttachmentReturns404ForEquipmentInAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-attachment-admin@example.com');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::OUTSIDER_EQUIPMENT_ID . '/attachments',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode([
        'fileName' => 'report.pdf',
        'content' => base64_encode('PDF content'),
        'mimeType' => 'application/pdf',
      ]),
    );

    self::assertSame(
      404,
      $client->getResponse()->getStatusCode(),
      'A caller must get 404 (not 403) for equipment outside the target organization.',
    );
  }

  #[Test]
  public function testDownloadAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'GET',
      '/api/organizations/' . self::DUMMY_UUID . '/equipment/' . self::DUMMY_UUID . '/attachments/' . self::DUMMY_UUID . '/download',
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, '.../attachments/{id}/download endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated download, got ' . $statusCode);
  }

  #[Test]
  public function testDownloadAttachmentServesBytesWithAttachmentDispositionAndNosniff(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-attachment-admin@example.com');

    $contents = '%PDF-1.4 attachment bytes for download round-trip';
    $attachmentId = $this->uploadAttachment($client, 'inspection.pdf', $contents, 'application/pdf');

    static::ensureKernelShutdown();
    $downloadClient = static::createClient();
    $this->loginAs($downloadClient, self::ADMIN_USER_ID, 'equipment-attachment-admin@example.com');
    $downloadClient->request(
      'GET',
      '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::EQUIPMENT_ID . '/attachments/' . $attachmentId . '/download',
    );

    $response = $downloadClient->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Download should succeed. Response: ' . $response->getContent());
    self::assertSame($contents, $response->getContent(), 'The download must serve the exact stored bytes.');
    self::assertSame('application/pdf', $response->headers->get('Content-Type'));

    $disposition = $response->headers->get('Content-Disposition');
    self::assertIsString($disposition);
    self::assertStringStartsWith(
      'attachment',
      $disposition,
      'An equipment attachment must NEVER be served with an inline Content-Disposition — see AttachmentDownloadResponder.',
    );

    self::assertSame(
      'nosniff',
      $response->headers->get('X-Content-Type-Options'),
      'The download response must carry X-Content-Type-Options: nosniff.',
    );
  }

  #[Test]
  public function testDownloadAttachmentRejectsMemberWithoutReadPermissionWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-attachment-admin@example.com');

    $attachmentId = $this->uploadAttachment($client, 'inspection.pdf', 'contents', 'application/pdf');

    static::ensureKernelShutdown();
    $memberClient = static::createClient();
    $this->loginAs($memberClient, self::PLAIN_MEMBER_USER_ID, 'equipment-attachment-plain-member@example.com');
    $memberClient->request(
      'GET',
      '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::EQUIPMENT_ID . '/attachments/' . $attachmentId . '/download',
    );

    self::assertSame(
      403,
      $memberClient->getResponse()->getStatusCode(),
      'A member without organization.equipment.read must get 403.',
    );
  }

  #[Test]
  public function testDownloadAttachmentReturns404ForACallerFromAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-attachment-admin@example.com');

    $attachmentId = $this->uploadAttachment($client, 'inspection.pdf', 'contents', 'application/pdf');

    static::ensureKernelShutdown();
    $outsiderClient = static::createClient();
    $this->loginAs($outsiderClient, self::OUTSIDER_USER_ID, 'equipment-attachment-outsider@example.com');
    $outsiderClient->request(
      'GET',
      '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::EQUIPMENT_ID . '/attachments/' . $attachmentId . '/download',
    );

    self::assertSame(
      404,
      $outsiderClient->getResponse()->getStatusCode(),
      'A caller outside the owning organization must get 404, not 403.',
    );
  }

  #[Test]
  public function testDownloadAttachmentReturns404WhenTheEquipmentIdInThePathDoesNotOwnTheAttachment(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-attachment-admin@example.com');

    $attachmentId = $this->uploadAttachment($client, 'inspection.pdf', 'contents', 'application/pdf');

    // OUTSIDER_EQUIPMENT_ID belongs to a different organization altogether,
    // so this exercises the SAME defense-in-depth outcome
    // GetEquipmentAttachmentContentHandler enforces whether the mismatch is
    // "wrong organization" or "wrong equipment within the right organization":
    // a path whose equipmentId does not own the requested attachment id
    // must never leak the file.
    static::ensureKernelShutdown();
    $wrongEquipmentClient = static::createClient();
    $this->loginAs($wrongEquipmentClient, self::ADMIN_USER_ID, 'equipment-attachment-admin@example.com');
    $wrongEquipmentClient->request(
      'GET',
      '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::OUTSIDER_EQUIPMENT_ID . '/attachments/' . $attachmentId . '/download',
    );

    self::assertSame(
      404,
      $wrongEquipmentClient->getResponse()->getStatusCode(),
      'An attachment requested through a mismatched equipmentId in the path must 404.',
    );
  }

  #[Test]
  public function testUploadMediaAcceptsAnAllowedMimeType(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-attachment-admin@example.com');

    $path = tempnam(sys_get_temp_dir(), 'equipment-media-');
    self::assertIsString($path);
    file_put_contents($path, $this->minimalPngBytes());

    $client->request(
      method: 'POST',
      uri: '/api/media',
      parameters: ['equipment' => '/api/equipment/' . self::EQUIPMENT_ID],
      files: ['file' => new UploadedFile(path: $path, originalName: 'photo.png', mimeType: 'image/png', test: true)],
    );

    $response = $client->getResponse();
    self::assertSame(201, $response->getStatusCode(), 'An allowed MIME type must succeed. Response: ' . $response->getContent());

    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame('photo.png', $decoded['fileName'] ?? null);
    self::assertSame('image/png', $decoded['mimeType'] ?? null);
  }

  #[Test]
  public function testUploadMediaRejectsADisallowedMimeTypeWith422(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-attachment-admin@example.com');

    $path = tempnam(sys_get_temp_dir(), 'equipment-media-exe-');
    self::assertIsString($path);
    file_put_contents($path, 'MZ');

    $client->request(
      method: 'POST',
      uri: '/api/media',
      parameters: ['equipment' => '/api/equipment/' . self::EQUIPMENT_ID],
      files: ['file' => new UploadedFile(path: $path, originalName: 'payload.exe', mimeType: 'application/x-msdownload', test: true)],
    );

    self::assertSame(
      expected: 422,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A disallowed MIME type must be rejected with 422. Response: ' . $client->getResponse()->getContent(),
    );
  }

  // The AttachmentConstraints::MAX_SIZE_BYTES (10 MiB) boundary is NOT
  // exercised here as a real multipart upload to /api/media: this test
  // environment's php.ini caps upload_max_filesize at 2M, well under the 10
  // MiB attachment policy, so Symfony's own filterFiles() rejects the upload
  // with UPLOAD_ERR_INI_SIZE (400) before MultipartAttachmentGuard is ever
  // reached — see FacilityAttachmentApiTest's identical note. The boundary
  // is covered as a unit test instead: MediaProcessorTest::testUploadRejectsAFileJustOverTheMaxSizeBeforeDispatch.

  /**
   * Method uploadAttachment.
   *
   * Uploads a base64 JSON attachment to {@see self::EQUIPMENT_ID} as the
   * currently logged-in client and returns the created attachment id.
   */
  private function uploadAttachment(KernelBrowser $client, string $fileName, string $contents, string $mimeType): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::EQUIPMENT_ID . '/attachments',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode([
        'fileName' => $fileName,
        'content' => base64_encode($contents),
        'mimeType' => $mimeType,
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(201, $response->getStatusCode(), 'Attachment upload should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertIsString($decoded['id']);

    return $decoded['id'];
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
   * A real 1x1 PNG. MultipartAttachmentGuard sniffs the real bytes.
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

    $now = new DateTimeImmutable('2026-08-19T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Equipment Attachment Test Org';
    $organization->slug = 'equipment-attachment-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Equipment Attachment Outsider Org';
    $outsiderOrganization->slug = 'equipment-attachment-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '770e8400-e29b-41d4-a716-446655480020';
    $adminRole->organization = $organization;
    $adminRole->name = 'equipment-attachment-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '770e8400-e29b-41d4-a716-446655480021';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'equipment-attachment-read-only';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without equipment.write access.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = '770e8400-e29b-41d4-a716-446655480024';
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'equipment-attachment-outsider-full-access';
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
    $plainMember->id = '770e8400-e29b-41d4-a716-446655480023';
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
    $outsiderMember->id = '770e8400-e29b-41d4-a716-446655480025';
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

  private function seedEquipment(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::EQUIPMENT_ID, self::OUTSIDER_EQUIPMENT_ID] as $equipmentId) {
      $existing = $entityManager->find(EquipmentRecord::class, $equipmentId);
      if ($existing instanceof EquipmentRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    /** @var OrganizationRecord $outsiderOrganization */
    $outsiderOrganization = $entityManager->getReference(OrganizationRecord::class, self::OUTSIDER_ORGANIZATION_ID);

    $now = new DateTimeImmutable('2026-08-19T00:00:00+00:00');

    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organization = $organization;
    $equipment->facilityId = null;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = 'in_stock';
    $equipment->recordStatus = 'published';
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;
    $entityManager->persist($equipment);

    $outsider = new EquipmentRecord();
    $outsider->id = self::OUTSIDER_EQUIPMENT_ID;
    $outsider->organization = $outsiderOrganization;
    $outsider->facilityId = null;
    $outsider->type = 'fire_extinguisher';
    $outsider->status = 'in_stock';
    $outsider->recordStatus = 'published';
    $outsider->createdAt = $now;
    $outsider->updatedAt = $now;
    $entityManager->persist($outsider);

    $entityManager->flush();
  }
}
