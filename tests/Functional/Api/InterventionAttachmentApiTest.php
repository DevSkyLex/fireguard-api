<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Outbound\FileStoragePort;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function base64_decode;
use function file_put_contents;
use function json_decode;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;

/**
 * Test InterventionAttachmentApiTest.
 *
 * Contract tests for the intervention attachment endpoints, including the
 * `GET /intervention-attachments/{id}/download` route (Phase 4b). The
 * download denial paths mirror the READ gate proven for the single-item
 * `GET /intervention-attachments/{id}` route and `GetInterventionWorkflowHandler`
 * (`InterventionAccessDeniedException` -> 403 for ANY caller missing
 * `organization.interventions.read`, whether they hold no permission in the
 * SAME organization or belong to a DIFFERENT organization entirely — the
 * flat `OrganizationAuthorizationPort::hasPermission()` check this module
 * uses everywhere for a path-id attachment record cannot distinguish the two
 * cases, so both map to the same 403 by design, not a missed 404).
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionAttachmentApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655480001';

  private const string ADMIN_USER_ID = '550e8400-e29b-41d4-a716-446655480002';

  private const string PLAIN_MEMBER_USER_ID = '550e8400-e29b-41d4-a716-446655480003';

  private const string OUTSIDER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655480004';

  private const string OUTSIDER_USER_ID = '550e8400-e29b-41d4-a716-446655480005';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655480010';

  #[Test]
  public function testUploadInterventionAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/interventions/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /interventions/{id}/attachments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /interventions/{id}/attachments, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListInterventionAttachmentsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/interventions/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /interventions/{id}/attachments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /interventions/{id}/attachments, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetInterventionAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/intervention-attachments/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /intervention-attachments/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /intervention-attachments/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testDeleteInterventionAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/intervention-attachments/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /intervention-attachments/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /intervention-attachments/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testDownloadInterventionAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/intervention-attachments/' . self::DUMMY_UUID . '/download');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /intervention-attachments/{id}/download endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /intervention-attachments/{id}/download, got ' . $statusCode,
    );
  }

  #[Test]
  public function testDownloadInterventionAttachmentReturns404ForAnUnknownId(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    $client->request('GET', '/api/intervention-attachments/' . self::DUMMY_UUID . '/download');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A non-existent attachment id must yield 404.',
    );
  }

  #[Test]
  public function testDownloadInterventionAttachmentRoundTripsTheExactBytesForAPublishedIntervention(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    // Uploaded while `draft` (upload/delete are phase-restricted), then
    // flipped to `published` directly — proving the DOWNLOAD route carries
    // NO phase restriction of its own: a published intervention's evidence
    // must stay downloadable even though it is otherwise fully immutable.
    $this->seedIntervention('draft');

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    // Accents and a space (both legal on every filesystem this suite runs
    // against, unlike a literal quote character on Windows storage) still
    // exercise the RFC 6266 filename*=UTF-8''... fallback below.
    $fileName = 'évidence spéciale (photo).jpg';
    $contents = $this->minimalJpegBytes();
    $attachmentId = $this->uploadAttachment($client, $fileName, $contents);

    $this->updateInterventionStatus('published');

    // The token set by loginUser() does not reliably survive a second
    // request on a reused client — a freshly authenticated client per
    // request, mirroring `OrganizationApiTest`'s documented convention.
    static::ensureKernelShutdown();
    $downloadClient = static::createClient();
    $this->loginAs($downloadClient, self::ADMIN_USER_ID, 'attachment-admin@example.com');
    $downloadClient->request('GET', '/api/intervention-attachments/' . $attachmentId . '/download');
    $response = $downloadClient->getResponse();

    self::assertSame(200, $response->getStatusCode(), 'Download should succeed. Response: ' . $response->getContent());
    self::assertSame($contents, $response->getContent(), 'The downloaded bytes must exactly match the uploaded bytes.');
    self::assertSame('image/jpeg', $response->headers->get('Content-Type'));
    self::assertSame((string) strlen($contents), $response->headers->get('Content-Length'));

    $disposition = $response->headers->get('Content-Disposition');
    self::assertIsString($disposition);
    self::assertStringStartsWith('attachment;', $disposition);
    // RFC 6266: the non-ASCII original name must survive via filename*=utf-8''...
    self::assertStringContainsString("filename*=utf-8''", $disposition);
    self::assertStringContainsString('%C3%A9vidence', $disposition, 'The percent-encoded accented name must be present.');
  }

  #[Test]
  public function testDownloadInterventionAttachmentRejectsMemberWithoutReadPermission(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('draft');

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');
    $attachmentId = $this->uploadAttachment($client, 'evidence.jpg', $this->minimalJpegBytes());

    // A freshly authenticated client per request — see the round-trip test
    // above for why a reused client's second loginUser() is not reliable.
    static::ensureKernelShutdown();
    $memberClient = static::createClient();
    $this->loginAs($memberClient, self::PLAIN_MEMBER_USER_ID, 'attachment-plain-member@example.com');
    $memberClient->request('GET', '/api/intervention-attachments/' . $attachmentId . '/download');

    self::assertSame(
      expected: 403,
      actual: $memberClient->getResponse()->getStatusCode(),
      message: 'A member without organization.interventions.read must get 403.',
    );
  }

  #[Test]
  public function testDownloadInterventionAttachmentRejectsACallerFromAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('draft');

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');
    $attachmentId = $this->uploadAttachment($client, 'evidence.jpg', $this->minimalJpegBytes());

    static::ensureKernelShutdown();
    $outsiderClient = static::createClient();
    $this->loginAs($outsiderClient, self::OUTSIDER_USER_ID, 'attachment-outsider@example.com');
    $outsiderClient->request('GET', '/api/intervention-attachments/' . $attachmentId . '/download');

    // Not a member of the owning organization at all: the flat
    // organization.interventions.read check this module uses for every
    // path-id attachment record cannot distinguish "wrong org" from "right
    // org, missing permission" — both fail the same hasPermission() call and
    // therefore map to the same 403 (see GetInterventionWorkflowHandler /
    // InterventionMediaProvider::getOne for the identical, pre-existing
    // pattern this endpoint mirrors).
    self::assertSame(
      expected: 403,
      actual: $outsiderClient->getResponse()->getStatusCode(),
      message: 'A caller outside the owning organization must be denied (403, mirroring the module-wide convention).',
    );
  }

  #[Test]
  public function testDownloadInterventionAttachmentReturns404WhenTheStoredFileIsMissing(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('draft');

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');
    $attachmentId = $this->uploadAttachment($client, 'evidence.jpg', $this->minimalJpegBytes());

    // Simulate a data-integrity gap: the DB row survives but the underlying
    // object is gone from storage (e.g. an out-of-band bucket deletion).
    /** @var FileStoragePort $fileStorage */
    $fileStorage = static::getContainer()->get(FileStoragePort::class);
    $fileStorage->delete('intervention/' . self::INTERVENTION_ID . '/attachments/' . $attachmentId . '_evidence.jpg');

    static::ensureKernelShutdown();
    $downloadClient = static::createClient();
    $this->loginAs($downloadClient, self::ADMIN_USER_ID, 'attachment-admin@example.com');
    $downloadClient->request('GET', '/api/intervention-attachments/' . $attachmentId . '/download');

    self::assertSame(
      expected: 404,
      actual: $downloadClient->getResponse()->getStatusCode(),
      message: 'A record whose stored file has gone missing must yield 404.',
    );
  }

  /**
   * Method minimalJpegBytes.
   *
   * A tiny (1x1) but genuinely valid JPEG payload — `MultipartAttachmentGuard`
   * derives the MIME type from the actual file content (via `finfo`), not
   * from the client-declared `Content-Type`, so an arbitrary string body
   * would be rejected with 422 before this endpoint is ever reached.
   */
  private function minimalJpegBytes(): string
  {
    $decoded = base64_decode(
      '/9j/4AAQSkZJRgABAQEAYABgAAD//gA+Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBkZWZhdWx0IHF1YWxpdHkK'
      . '/9sAQwAIBgYHBgUIBwcHCQkICgwUDQwLCwwZEhMPFB0aHx4dGhwcICQuJyAiLCMcHCg3KSwwMTQ0NB8nOT04MjwuMzQy/9sAQwEJCQkMCwwYDQ0Y'
      . 'MiEcITIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy/8AAEQgAAQABAwEiAAIRAQMRAf/EAB8AAAEFAQEB'
      . 'AQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYX'
      . 'GBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrC'
      . 'w8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQH'
      . 'BQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2Rl'
      . 'ZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5'
      . '+v/aAAwDAQACEQMRAD8A+f6KKKAP/9k=',
      true,
    );
    self::assertIsString($decoded);

    return $decoded;
  }

  /**
   * Method uploadAttachment.
   *
   * Uploads a real multipart file to {@see self::INTERVENTION_ID} as the
   * currently logged-in client and returns the created attachment id.
   */
  private function uploadAttachment(KernelBrowser $client, string $fileName, string $contents): string
  {
    $path = tempnam(sys_get_temp_dir(), 'ivn-attach-');
    self::assertIsString($path);
    file_put_contents($path, $contents);

    $uploadedFile = new UploadedFile(
      path: $path,
      originalName: $fileName,
      mimeType: 'image/jpeg',
      test: true,
    );

    $client->request(
      method: 'POST',
      uri: '/api/interventions/' . self::INTERVENTION_ID . '/attachments',
      files: ['file' => $uploadedFile],
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
   * Method seedOrganization.
   *
   * Seeds (idempotently) an organization with an admin member (permissions
   * `['*']`) and a plain member (`organization.read` only, no
   * `organization.interventions.read`), plus a second, unrelated
   * organization with its own member — the "outside scope" caller.
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
    $organization->name = 'Attachment Download Test Org';
    $organization->slug = 'attachment-download-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Attachment Download Outsider Org';
    $outsiderOrganization->slug = 'attachment-download-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '550e8400-e29b-41d4-a716-446655480020';
    $adminRole->organization = $organization;
    $adminRole->name = 'attachment-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '550e8400-e29b-41d4-a716-446655480021';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'attachment-read-only';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without intervention read access.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = '550e8400-e29b-41d4-a716-446655480024';
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'attachment-outsider-full-access';
    $outsiderRole->permissions = ['*'];
    $outsiderRole->description = 'Functional-test-only role for the unrelated organization.';
    $outsiderRole->isSystem = false;
    $outsiderRole->createdAt = $now;
    $entityManager->persist($outsiderRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = '550e8400-e29b-41d4-a716-446655480022';
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
    $plainMember->id = '550e8400-e29b-41d4-a716-446655480023';
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
    $outsiderMember->id = '550e8400-e29b-41d4-a716-446655480025';
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
   * Method seedIntervention.
   *
   * Seeds (idempotently) a single intervention owned by
   * {@see self::ORGANIZATION_ID} in the given status.
   */
  private function seedIntervention(string $status): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $existing = $entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    if ($existing instanceof InterventionRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION_ID;
    $intervention->organization = $organization;
    $intervention->type = 'site_setup';
    $intervention->name = 'Attachment Download Test Intervention';
    $intervention->number = 900;
    $intervention->status = $status;
    $intervention->createdAt = $now;
    $intervention->updatedAt = $now;
    $entityManager->persist($intervention);
    $entityManager->flush();
  }

  /**
   * Method updateInterventionStatus.
   *
   * Flips {@see self::INTERVENTION_ID}'s status in place — unlike
   * {@see self::seedIntervention()}, this never deletes/recreates the row,
   * which would cascade-delete any attachment already uploaded against it.
   */
  private function updateInterventionStatus(string $status): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $intervention = $entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $intervention);
    $intervention->status = $status;
    $entityManager->flush();
  }
}
