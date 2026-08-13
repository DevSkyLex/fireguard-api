<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionWorkItemRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Outbound\FileStoragePort;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function array_filter;
use function array_values;
use function base64_decode;
use function file_put_contents;
use function is_array;
use function is_string;
use function json_decode;
use function str_replace;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;

/**
 * Test InterventionAttachmentApiTest.
 *
 * Contract tests for the intervention attachment endpoints, including the
 * `GET /intervention-attachments/{id}/download` route (Phase 4b). The
 * download denial paths mirror the READ gate proven for the single-item
 * `GET /intervention-attachments/{id}` route and `GetInterventionWorkflowHandler`,
 * and split the two denials the way the module now does everywhere:
 *
 * - a member of the OWNING organization who lacks
 *   `organization.interventions.read` gets **403** — they may know the
 *   record exists, they simply may not read it;
 * - a caller with no active membership in the owning organization gets
 *   **404**, byte-identical to the response for an attachment id that does
 *   not exist at all. Returning 403 there would confirm the record exists to
 *   someone who must not even learn that much.
 *
 * `OrganizationAuthorizationPort::resolveAccess()` is what carries that
 * distinction; the flat `hasPermission()` it replaced could not.
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

  private const string ADMIN_MEMBER_ID = '550e8400-e29b-41d4-a716-446655480022';

  private const string PLAIN_MEMBER_USER_ID = '550e8400-e29b-41d4-a716-446655480003';

  private const string OUTSIDER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655480004';

  private const string OUTSIDER_USER_ID = '550e8400-e29b-41d4-a716-446655480005';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655480010';

  private const string OTHER_INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655480011';

  private const string WORK_ITEM_ID = '550e8400-e29b-41d4-a716-446655480030';

  private const string OTHER_WORK_ITEM_ID = '550e8400-e29b-41d4-a716-446655480031';

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
  public function testDownloadInterventionAttachmentReturns404ForACallerFromAnotherOrganization(): void
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

    // No active membership in the owning organization: the attachment must be
    // invisible, not merely forbidden. 403 here would confirm to an outsider
    // that this attachment id is real — the existence oracle this route's
    // security review raised. `resolveAccess()` reports OUTSIDE_SCOPE and the
    // handler throws the same InterventionAttachmentNotFoundException an
    // unknown id produces.
    self::assertSame(
      expected: 404,
      actual: $outsiderClient->getResponse()->getStatusCode(),
      message: 'A caller outside the owning organization must get 404, not 403.',
    );
  }

  #[Test]
  public function testDownloadResponseForAnOutsiderIsIndistinguishableFromAnUnknownAttachment(): void
  {
    // The status code alone is not the whole contract: if the two responses
    // differed in body or headers, the oracle would survive the 404. Compare
    // a real attachment fetched by an outsider against an id that exists
    // nowhere, requested by the same outsider.
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('draft');

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');
    $attachmentId = $this->uploadAttachment($client, 'evidence.jpg', $this->minimalJpegBytes());

    static::ensureKernelShutdown();
    $outsiderClient = static::createClient();
    $this->loginAs($outsiderClient, self::OUTSIDER_USER_ID, 'attachment-outsider@example.com');

    $outsiderClient->request('GET', '/api/intervention-attachments/' . $attachmentId . '/download');
    $existingStatus = $outsiderClient->getResponse()->getStatusCode();
    $existingProblem = $this->normalizedProblem($outsiderClient, $attachmentId);

    // A fresh client per request: the harness token does not survive a second
    // request on the same client (same workaround as the sibling tests).
    static::ensureKernelShutdown();
    $secondOutsiderClient = static::createClient();
    $this->loginAs($secondOutsiderClient, self::OUTSIDER_USER_ID, 'attachment-outsider@example.com');

    $secondOutsiderClient->request('GET', '/api/intervention-attachments/' . self::DUMMY_UUID . '/download');
    $unknownStatus = $secondOutsiderClient->getResponse()->getStatusCode();
    $unknownProblem = $this->normalizedProblem($secondOutsiderClient, self::DUMMY_UUID);

    self::assertSame(404, $existingStatus);
    self::assertSame(
      expected: $unknownStatus,
      actual: $existingStatus,
      message: 'An outsider must not tell a real attachment from an imaginary one by status.',
    );
    self::assertSame(
      expected: $unknownProblem,
      actual: $existingProblem,
      message: 'An outsider must not tell a real attachment from an imaginary one by the error body.',
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

  #[Test]
  public function testUploadWithWorkItemIdRoundTripsIntoTheOutputAndTheFilterNarrowsAndTheWorkItemOutputExposesTheEvidenceCount(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('draft');
    $this->seedWorkItem(self::WORK_ITEM_ID, self::INTERVENTION_ID);
    $this->seedWorkItem(self::OTHER_WORK_ITEM_ID, self::INTERVENTION_ID);

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    $path = tempnam(sys_get_temp_dir(), 'ivn-attach-');
    self::assertIsString($path);
    file_put_contents($path, $this->minimalJpegBytes());
    $uploadedFile = new UploadedFile(path: $path, originalName: 'evidence.jpg', mimeType: 'image/jpeg', test: true);

    $client->request(
      method: 'POST',
      uri: '/api/interventions/' . self::INTERVENTION_ID . '/attachments',
      parameters: ['workItemId' => self::WORK_ITEM_ID],
      files: ['file' => $uploadedFile],
    );

    $uploadResponse = $client->getResponse();
    self::assertSame(201, $uploadResponse->getStatusCode(), 'Upload should succeed. Response: ' . $uploadResponse->getContent());
    $decoded = json_decode((string) $uploadResponse->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame(self::WORK_ITEM_ID, $decoded['workItemId'] ?? null);

    // A freshly authenticated client per request — the token set by
    // loginUser() does not reliably survive a second request on a reused
    // client, per this file's established convention (see the download
    // round-trip test above).
    static::ensureKernelShutdown();
    $listClient = static::createClient();
    $this->loginAs($listClient, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    // The workItem filter narrows the list to only this work item's attachment.
    $listClient->request('GET', '/api/interventions/' . self::INTERVENTION_ID . '/attachments?workItem=' . self::WORK_ITEM_ID);
    $listResponse = $listClient->getResponse();
    self::assertSame(200, $listResponse->getStatusCode());
    $listDecoded = json_decode((string) $listResponse->getContent(), true);
    self::assertIsArray($listDecoded);
    self::assertIsArray($listDecoded['member']);
    self::assertCount(1, $listDecoded['member']);
    self::assertIsArray($listDecoded['member'][0]);
    self::assertSame(self::WORK_ITEM_ID, $listDecoded['member'][0]['workItemId'] ?? null);

    static::ensureKernelShutdown();
    $emptyListClient = static::createClient();
    $this->loginAs($emptyListClient, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    // The other work item, which received no attachment, narrows to zero.
    $emptyListClient->request('GET', '/api/interventions/' . self::INTERVENTION_ID . '/attachments?workItem=' . self::OTHER_WORK_ITEM_ID);
    $emptyListDecoded = json_decode((string) $emptyListClient->getResponse()->getContent(), true);
    self::assertIsArray($emptyListDecoded);
    self::assertIsArray($emptyListDecoded['member']);
    self::assertCount(0, $emptyListDecoded['member']);

    static::ensureKernelShutdown();
    $workItemClient = static::createClient();
    $this->loginAs($workItemClient, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    // The work item output surfaces the evidence count.
    $workItemClient->request('GET', '/api/intervention-work-items/' . self::WORK_ITEM_ID);
    $workItemResponse = $workItemClient->getResponse();
    self::assertSame(200, $workItemResponse->getStatusCode());
    $workItemDecoded = json_decode((string) $workItemResponse->getContent(), true);
    self::assertIsArray($workItemDecoded);
    self::assertSame(1, $workItemDecoded['evidenceCount'] ?? null);
  }

  #[Test]
  public function testUploadWithAWorkItemIdFromAnotherInterventionIsRejectedWith422(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('draft');
    $this->seedWorkItem(self::WORK_ITEM_ID, self::INTERVENTION_ID);

    // A second intervention with its own work item — the cross-scope case.
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $existingOther = $entityManager->find(InterventionRecord::class, self::OTHER_INTERVENTION_ID);
    if ($existingOther instanceof InterventionRecord) {
      $entityManager->remove($existingOther);
      $entityManager->flush();
    }
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $otherIntervention = new InterventionRecord();
    $otherIntervention->id = self::OTHER_INTERVENTION_ID;
    $otherIntervention->organization = $organization;
    $otherIntervention->type = 'site_setup';
    $otherIntervention->name = 'Attachment Download Test Other Intervention';
    $otherIntervention->number = 901;
    $otherIntervention->status = 'draft';
    $otherIntervention->createdAt = $now;
    $otherIntervention->updatedAt = $now;
    $entityManager->persist($otherIntervention);
    $entityManager->flush();
    $this->seedWorkItem(self::OTHER_WORK_ITEM_ID, self::OTHER_INTERVENTION_ID);

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    $path = tempnam(sys_get_temp_dir(), 'ivn-attach-');
    self::assertIsString($path);
    file_put_contents($path, $this->minimalJpegBytes());
    $uploadedFile = new UploadedFile(path: $path, originalName: 'evidence.jpg', mimeType: 'image/jpeg', test: true);

    $client->request(
      method: 'POST',
      uri: '/api/interventions/' . self::INTERVENTION_ID . '/attachments',
      parameters: ['workItemId' => self::OTHER_WORK_ITEM_ID],
      files: ['file' => $uploadedFile],
    );

    self::assertSame(
      expected: 422,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A workItemId from another intervention must be rejected with 422. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testUploadSignatureRoundTripsTheKindIntoTheOutputAndFlipsHasSignature(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('in_progress');

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    $path = tempnam(sys_get_temp_dir(), 'ivn-signature-');
    self::assertIsString($path);
    file_put_contents($path, $this->minimalJpegBytes());
    $uploadedFile = new UploadedFile(path: $path, originalName: 'signature.jpg', mimeType: 'image/jpeg', test: true);

    $client->request(
      method: 'POST',
      uri: '/api/interventions/' . self::INTERVENTION_ID . '/attachments',
      parameters: ['kind' => 'signature'],
      files: ['file' => $uploadedFile],
    );

    $uploadResponse = $client->getResponse();
    self::assertSame(201, $uploadResponse->getStatusCode(), 'Signature upload should succeed. Response: ' . $uploadResponse->getContent());
    $decoded = json_decode((string) $uploadResponse->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame('signature', $decoded['kind'] ?? null);

    static::ensureKernelShutdown();
    $interventionClient = static::createClient();
    $this->loginAs($interventionClient, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    $interventionClient->request('GET', '/api/interventions/' . self::INTERVENTION_ID);
    $interventionDecoded = json_decode((string) $interventionClient->getResponse()->getContent(), true);
    self::assertIsArray($interventionDecoded);
    self::assertTrue($interventionDecoded['hasSignature'] ?? null, 'hasSignature must flip to true once a signature is uploaded.');
  }

  #[Test]
  public function testUploadWithoutAKindDefaultsToFile(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('draft');

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');
    $attachmentId = $this->uploadAttachment($client, 'evidence.jpg', $this->minimalJpegBytes());

    static::ensureKernelShutdown();
    $getClient = static::createClient();
    $this->loginAs($getClient, self::ADMIN_USER_ID, 'attachment-admin@example.com');
    $getClient->request('GET', '/api/intervention-attachments/' . $attachmentId);

    $decoded = json_decode((string) $getClient->getResponse()->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame('file', $decoded['kind'] ?? null);
  }

  #[Test]
  public function testUploadWithAnUnknownKindIsRejectedWith422(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('in_progress');

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    $path = tempnam(sys_get_temp_dir(), 'ivn-signature-');
    self::assertIsString($path);
    file_put_contents($path, $this->minimalJpegBytes());
    $uploadedFile = new UploadedFile(path: $path, originalName: 'signature.jpg', mimeType: 'image/jpeg', test: true);

    $client->request(
      method: 'POST',
      uri: '/api/interventions/' . self::INTERVENTION_ID . '/attachments',
      parameters: ['kind' => 'not-a-real-kind'],
      files: ['file' => $uploadedFile],
    );

    self::assertSame(
      expected: 422,
      actual: $client->getResponse()->getStatusCode(),
      message: 'An unknown attachment kind must be rejected with 422. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testUploadAPdfAsASignatureIsRejectedWith422(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('in_progress');

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    // A real-but-empty-content PDF: MultipartAttachmentGuard only enforces
    // the mime allow-list, the signature-specific image-only rule is the
    // handler's business, so the PDF must clear the guard and be rejected
    // downstream instead.
    $path = tempnam(sys_get_temp_dir(), 'ivn-signature-pdf-');
    self::assertIsString($path);
    file_put_contents($path, "%PDF-1.4\n%%EOF");
    $uploadedFile = new UploadedFile(path: $path, originalName: 'signature.pdf', mimeType: 'application/pdf', test: true);

    $client->request(
      method: 'POST',
      uri: '/api/interventions/' . self::INTERVENTION_ID . '/attachments',
      parameters: ['kind' => 'signature'],
      files: ['file' => $uploadedFile],
    );

    self::assertSame(
      expected: 422,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A PDF uploaded as a signature must be rejected with 422. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testUploadASignatureOutsideTheAllowedPhasesIsRejectedWith409(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    // "planned" is mutable (uploads are otherwise allowed) but not one of the
    // submission phases the completion signature is scoped to.
    $this->seedIntervention('planned');

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    $path = tempnam(sys_get_temp_dir(), 'ivn-signature-');
    self::assertIsString($path);
    file_put_contents($path, $this->minimalJpegBytes());
    $uploadedFile = new UploadedFile(path: $path, originalName: 'signature.jpg', mimeType: 'image/jpeg', test: true);

    $client->request(
      method: 'POST',
      uri: '/api/interventions/' . self::INTERVENTION_ID . '/attachments',
      parameters: ['kind' => 'signature'],
      files: ['file' => $uploadedFile],
    );

    self::assertSame(
      expected: 409,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A signature uploaded outside in_progress/changes_requested must be rejected with 409. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testReuploadingASignatureReplacesThePreviousOne(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('in_progress');

    $this->loginAs($client, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    $path = tempnam(sys_get_temp_dir(), 'ivn-signature-');
    self::assertIsString($path);
    file_put_contents($path, $this->minimalJpegBytes());
    $firstFile = new UploadedFile(path: $path, originalName: 'signature-1.jpg', mimeType: 'image/jpeg', test: true);

    $client->request(
      method: 'POST',
      uri: '/api/interventions/' . self::INTERVENTION_ID . '/attachments',
      parameters: ['kind' => 'signature'],
      files: ['file' => $firstFile],
    );
    $firstDecoded = json_decode((string) $client->getResponse()->getContent(), true);
    self::assertIsArray($firstDecoded);
    self::assertIsString($firstDecoded['id']);
    $firstId = $firstDecoded['id'];

    static::ensureKernelShutdown();
    $secondClient = static::createClient();
    $this->loginAs($secondClient, self::ADMIN_USER_ID, 'attachment-admin@example.com');

    $secondPath = tempnam(sys_get_temp_dir(), 'ivn-signature-');
    self::assertIsString($secondPath);
    file_put_contents($secondPath, $this->minimalJpegBytes());
    $secondFile = new UploadedFile(path: $secondPath, originalName: 'signature-2.jpg', mimeType: 'image/jpeg', test: true);

    $secondClient->request(
      method: 'POST',
      uri: '/api/interventions/' . self::INTERVENTION_ID . '/attachments',
      parameters: ['kind' => 'signature'],
      files: ['file' => $secondFile],
    );
    $secondResponse = $secondClient->getResponse();
    self::assertSame(201, $secondResponse->getStatusCode(), 'Second signature upload should succeed. Response: ' . $secondResponse->getContent());
    $secondDecoded = json_decode((string) $secondResponse->getContent(), true);
    self::assertIsArray($secondDecoded);
    self::assertIsString($secondDecoded['id']);
    $secondId = $secondDecoded['id'];
    self::assertNotSame($firstId, $secondId, 'A re-uploaded signature must mint a new attachment id.');

    // The first signature must be gone: fetching it by id now 404s.
    static::ensureKernelShutdown();
    $getClient = static::createClient();
    $this->loginAs($getClient, self::ADMIN_USER_ID, 'attachment-admin@example.com');
    $getClient->request('GET', '/api/intervention-attachments/' . $firstId);
    self::assertSame(404, $getClient->getResponse()->getStatusCode(), 'The replaced signature must no longer exist.');

    // Exactly one signature-kind attachment remains for the intervention.
    static::ensureKernelShutdown();
    $listClient = static::createClient();
    $this->loginAs($listClient, self::ADMIN_USER_ID, 'attachment-admin@example.com');
    $listClient->request('GET', '/api/interventions/' . self::INTERVENTION_ID . '/attachments');
    $listDecoded = json_decode((string) $listClient->getResponse()->getContent(), true);
    self::assertIsArray($listDecoded);
    self::assertIsArray($listDecoded['member']);
    $signatures = array_values(array_filter(
      $listDecoded['member'],
      static fn ($attachment): bool => is_array($attachment) && ('signature' === ($attachment['kind'] ?? null)),
    ));
    self::assertCount(1, $signatures);
    self::assertSame($secondId, $signatures[0]['id'] ?? null);
  }

  /**
   * Method normalizedProblem.
   *
   * The client-visible error contract of the last response, with the
   * requested identifier folded to a placeholder. The identifier is echoed
   * back in `detail` and is information the caller already supplied, so it
   * carries no signal; everything else must match between a real record and
   * an imaginary one.
   *
   * @param KernelBrowser $client the browser holding the response
   * @param string $requestedId the identifier that was requested
   *
   * @return array<string, mixed> the normalized problem fields
   */
  private function normalizedProblem(KernelBrowser $client, string $requestedId): array
  {
    $decoded = json_decode((string) $client->getResponse()->getContent(), true);
    if (!is_array($decoded)) {
      return [];
    }

    $problem = [];
    foreach (['status', 'type', 'title', 'detail'] as $field) {
      $value = $decoded[$field] ?? null;
      $problem[$field] = is_string($value) ? str_replace($requestedId, '{id}', $value) : $value;
    }

    return $problem;
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
   * {@see self::ORGANIZATION_ID} in the given status, with the admin member
   * ({@see self::ADMIN_MEMBER_ID}) as responsible — a non-`draft` status
   * routes through `InterventionMemberPolicy::assertCanExecuteIntervention()`,
   * which requires the caller to be the responsible member or a participant.
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
    $intervention->responsibleId = self::ADMIN_MEMBER_ID;
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

  /**
   * Method seedWorkItem.
   *
   * Seeds a `planned` work item on the given intervention (Phase 5d.1
   * per-work-item evidence tests).
   */
  private function seedWorkItem(string $id, string $interventionId): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $existing = $entityManager->find(InterventionWorkItemRecord::class, $id);
    if ($existing instanceof InterventionWorkItemRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $workItem = new InterventionWorkItemRecord();
    $workItem->id = $id;
    $workItem->intervention = $entityManager->getReference(InterventionRecord::class, $interventionId);
    $workItem->action = 'site_setup';
    $workItem->source = 'planned';
    $workItem->status = 'planned';
    $workItem->required = true;
    $workItem->createdAt = $now;
    $workItem->updatedAt = $now;
    $entityManager->persist($workItem);
    $entityManager->flush();
  }
}
