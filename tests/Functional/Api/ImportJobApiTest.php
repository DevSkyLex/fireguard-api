<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Import\Application\UseCase\Command\ProcessImportJob\{ProcessImportJobCommand, ProcessImportJobHandler};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function file_put_contents;
use function is_string;
use function json_decode;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Test ImportJobApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImportJobApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testCreateImportJobRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/imports', server: [
      'CONTENT_TYPE' => 'multipart/form-data',
    ]);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /imports endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /imports, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListImportJobsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/imports?organization=' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /imports endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /imports, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetImportJobRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/imports/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /imports/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /imports/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testDryRunFacilityImportReportsEveryRowAndPersistsNothing(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655480100';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655480101';

    $this->seedFullAccessOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    // Row 1: a valid root facility (has a code other rows can reference).
    // Row 2: references row 1's code — a parent that would itself be
    // created earlier in the same file, not yet in the database.
    // Row 3: missing the required `name` column — a row-level failure.
    $csv = "type,name,code,parentCode\n"
      . "site,HQ,HQ,\n"
      . "zone,Annex,,HQ\n"
      . "site,,,\n";

    $path = tempnam(sys_get_temp_dir(), 'import-dry-run-');
    self::assertIsString($path);
    file_put_contents($path, $csv);

    try {
      $client->request('POST', '/api/imports', [
        'organization' => $organizationId,
        'kind' => 'facility',
        'dryRun' => 'true',
      ], [
        'file' => new UploadedFile($path, 'facilities.csv', 'text/csv', null, true),
      ], server: [
        'HTTP_ACCEPT' => 'application/ld+json',
      ]);
    } finally {
      unlink($path);
    }

    $createResponse = $client->getResponse();
    self::assertSame(202, $createResponse->getStatusCode(), 'Response: ' . $createResponse->getContent());

    $created = json_decode($createResponse->getContent() ?: '{}', true);
    self::assertIsArray($created);
    self::assertTrue($created['dryRun'] ?? null);
    $importJobId = $created['id'];
    self::assertIsString($importJobId);

    // The job was only enqueued (async transport), not processed — run the
    // same handler the worker would, synchronously, to make the report
    // available for this test without a running consumer. A fresh kernel
    // boot (own EntityManager) is deliberate: `claim()` writes through raw
    // DBAL, and reusing the create request's EntityManager would answer a
    // stale `pending` from its identity map instead of the row the raw
    // UPDATE actually left `processing` — exactly what a separate worker
    // process never risks in production.
    static::ensureKernelShutdown();
    static::createClient();
    /** @var ProcessImportJobHandler $processHandler */
    $processHandler = static::getContainer()->get(ProcessImportJobHandler::class);
    $processHandler->__invoke(new ProcessImportJobCommand($importJobId));

    static::ensureKernelShutdown();
    $readClient = static::createClient();
    $readClient->loginUser($this->securityUser($ownerUserId), 'api');
    $readClient->request('GET', '/api/imports/' . $importJobId, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $getResponse = $readClient->getResponse();
    self::assertSame(200, $getResponse->getStatusCode(), 'Response: ' . $getResponse->getContent());

    $body = json_decode($getResponse->getContent() ?: '{}', true);
    self::assertIsArray($body);
    $jobError = $body['jobError'] ?? null;
    self::assertSame('completed', $body['status'] ?? null, 'jobError: ' . (is_string($jobError) ? $jobError : 'null'));
    self::assertTrue($body['dryRun'] ?? null);
    self::assertSame(2, $body['successfulRows'] ?? null);
    self::assertSame(1, $body['failedRows'] ?? null);

    $report = $body['errorReport'] ?? [];
    self::assertIsArray($report);
    self::assertCount(3, $report);
    self::assertIsArray($report[0]);
    self::assertIsArray($report[1]);
    self::assertIsArray($report[2]);
    self::assertSame(1, $report[0]['rowNumber']);
    self::assertSame('would_create', $report[0]['code']);
    self::assertSame(2, $report[1]['rowNumber']);
    self::assertSame('would_create', $report[1]['code']);
    self::assertSame(3, $report[2]['rowNumber']);
    self::assertSame('missing_required', $report[2]['code']);

    // The negative assertion is the point: a dry run must persist nothing.
    /** @var EntityManagerInterface $mainEntityManager */
    $mainEntityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $facilityCount = $mainEntityManager->createQueryBuilder()
      ->select('COUNT(f.id)')
      ->from(FacilityRecord::class, 'f')
      ->where('f.organization = :organizationId')
      ->setParameter('organizationId', $organizationId)
      ->getQuery()
      ->getSingleScalarResult();
    self::assertSame(0, (int) $facilityCount);
  }

  #[Test]
  public function testRealFacilityImportPersistsTheProvisionedFacilities(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655480200';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655480201';

    $this->seedFullAccessOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $csv = "type,name\nsite,Real Run HQ\n";

    $path = tempnam(sys_get_temp_dir(), 'import-real-run-');
    self::assertIsString($path);
    file_put_contents($path, $csv);

    try {
      $client->request('POST', '/api/imports', [
        'organization' => $organizationId,
        'kind' => 'facility',
      ], [
        'file' => new UploadedFile($path, 'facilities.csv', 'text/csv', null, true),
      ], server: [
        'HTTP_ACCEPT' => 'application/ld+json',
      ]);
    } finally {
      unlink($path);
    }

    $createResponse = $client->getResponse();
    self::assertSame(202, $createResponse->getStatusCode(), 'Response: ' . $createResponse->getContent());

    $created = json_decode($createResponse->getContent() ?: '{}', true);
    self::assertIsArray($created);
    self::assertFalse($created['dryRun'] ?? null);
    $importJobId = $created['id'];
    self::assertIsString($importJobId);

    // See the dry-run test above for why this needs a fresh kernel boot
    // (own EntityManager) rather than reusing the create request's.
    static::ensureKernelShutdown();
    static::createClient();
    /** @var ProcessImportJobHandler $processHandler */
    $processHandler = static::getContainer()->get(ProcessImportJobHandler::class);
    $processHandler->__invoke(new ProcessImportJobCommand($importJobId));

    /** @var EntityManagerInterface $mainEntityManager */
    $mainEntityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $facilityCount = $mainEntityManager->createQueryBuilder()
      ->select('COUNT(f.id)')
      ->from(FacilityRecord::class, 'f')
      ->where('f.organization = :organizationId')
      ->setParameter('organizationId', $organizationId)
      ->getQuery()
      ->getSingleScalarResult();
    self::assertSame(1, (int) $facilityCount);
  }

  #[Test]
  public function testGetImportJobAnswersNotFoundForAJobInAnotherOrganization(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655480300';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655480301';
    $outsiderUserId = '550e8400-e29b-41d4-a716-446655480302';

    $importJobId = $this->createImportJobAs($organizationId, $ownerUserId);

    // 404, not 403: a 403 would confirm to a caller from outside the owning
    // organization that this job id exists.
    static::ensureKernelShutdown();
    $client = static::createClient();
    $client->loginUser($this->securityUser($outsiderUserId), 'api');
    $client->request('GET', '/api/imports/' . $importJobId, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(404, $response->getStatusCode(), 'Response: ' . $response->getContent());
  }

  #[Test]
  public function testGetImportJobIsForbiddenForAMemberWithoutTheKindReadPermission(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655480310';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655480311';
    $unentitledUserId = '550e8400-e29b-41d4-a716-446655480312';

    $importJobId = $this->createImportJobAs($organizationId, $ownerUserId);

    // A member of the owning organization holding no permission at all gets
    // 403 — the job's existence is not a secret from someone already inside.
    static::ensureKernelShutdown();
    $client = static::createClient();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->seedMemberWithPermissions($entityManager, $organizationId, $unentitledUserId, []);
    $entityManager->flush();

    $client->loginUser($this->securityUser($unentitledUserId), 'api');
    $client->request('GET', '/api/imports/' . $importJobId, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(403, $response->getStatusCode(), 'Response: ' . $response->getContent());
  }

  #[Test]
  public function testListImportJobsAnswersNotFoundForAnotherOrganization(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655480320';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655480321';
    $outsiderUserId = '550e8400-e29b-41d4-a716-446655480322';

    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->seedFullAccessOrganization(
      $entityManager,
      $organizationId,
      $ownerUserId,
      new DateTimeImmutable('2026-08-19T00:00:00+00:00'),
    );
    $entityManager->flush();

    $client->loginUser($this->securityUser($outsiderUserId), 'api');
    $client->request('GET', '/api/imports?organization=' . $organizationId, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(404, $response->getStatusCode(), 'Response: ' . $response->getContent());
  }

  #[Test]
  public function testListImportJobsIsForbiddenForAMemberHoldingNeitherReadPermission(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655480330';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655480331';
    $unentitledUserId = '550e8400-e29b-41d4-a716-446655480332';

    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->seedFullAccessOrganization(
      $entityManager,
      $organizationId,
      $ownerUserId,
      new DateTimeImmutable('2026-08-19T00:00:00+00:00'),
    );
    $this->seedMemberWithPermissions($entityManager, $organizationId, $unentitledUserId, []);
    $entityManager->flush();

    $client->loginUser($this->securityUser($unentitledUserId), 'api');
    $client->request('GET', '/api/imports?organization=' . $organizationId, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(403, $response->getStatusCode(), 'Response: ' . $response->getContent());
  }

  #[Test]
  public function testCreateImportJobAnswersNotFoundForAnotherOrganization(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655480340';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655480341';
    $outsiderUserId = '550e8400-e29b-41d4-a716-446655480342';

    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->seedFullAccessOrganization(
      $entityManager,
      $organizationId,
      $ownerUserId,
      new DateTimeImmutable('2026-08-19T00:00:00+00:00'),
    );
    $entityManager->flush();

    $client->loginUser($this->securityUser($outsiderUserId), 'api');
    $response = $this->uploadFacilityCsv($client, $organizationId);

    self::assertSame(404, $response, 'Expected 404 for an upload targeting a foreign organization.');
  }

  #[Test]
  public function testCreateImportJobIsForbiddenForAMemberWithoutTheKindWritePermission(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655480350';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655480351';
    $unentitledUserId = '550e8400-e29b-41d4-a716-446655480352';

    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->seedFullAccessOrganization(
      $entityManager,
      $organizationId,
      $ownerUserId,
      new DateTimeImmutable('2026-08-19T00:00:00+00:00'),
    );
    // Read-only member: inside the organization, but not entitled to write.
    $this->seedMemberWithPermissions(
      $entityManager,
      $organizationId,
      $unentitledUserId,
      ['organization.facilities.read'],
    );
    $entityManager->flush();

    $client->loginUser($this->securityUser($unentitledUserId), 'api');
    $response = $this->uploadFacilityCsv($client, $organizationId);

    self::assertSame(403, $response, 'Expected 403 for a member lacking organization.facilities.write.');
  }

  /**
   * Uploads a minimal valid facility CSV and returns the response status.
   */
  private function uploadFacilityCsv(KernelBrowser $client, string $organizationId): int
  {
    $path = tempnam(sys_get_temp_dir(), 'import-denial-');
    self::assertIsString($path);
    file_put_contents($path, "type,name\nsite,Denied HQ\n");

    try {
      $client->request('POST', '/api/imports', [
        'organization' => $organizationId,
        'kind' => 'facility',
      ], [
        'file' => new UploadedFile($path, 'facilities.csv', 'text/csv', null, true),
      ], server: [
        'HTTP_ACCEPT' => 'application/ld+json',
      ]);
    } finally {
      unlink($path);
    }

    return $client->getResponse()->getStatusCode();
  }

  /**
   * Seeds a full-access organization and returns the id of an import job
   * created in it by its owner.
   */
  private function createImportJobAs(string $organizationId, string $ownerUserId): string
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->seedFullAccessOrganization(
      $entityManager,
      $organizationId,
      $ownerUserId,
      new DateTimeImmutable('2026-08-19T00:00:00+00:00'),
    );
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');
    $status = $this->uploadFacilityCsv($client, $organizationId);
    self::assertSame(202, $status, 'Response: ' . $client->getResponse()->getContent());

    $created = json_decode($client->getResponse()->getContent() ?: '{}', true);
    self::assertIsArray($created);
    $importJobId = $created['id'] ?? null;
    self::assertIsString($importJobId);

    return $importJobId;
  }

  /**
   * Adds an active member to an existing organization, holding exactly the
   * given permissions (an empty list means a member entitled to nothing).
   *
   * @param list<string> $permissions the role's permission names
   */
  private function seedMemberWithPermissions(
    EntityManagerInterface $entityManager,
    string $organizationId,
    string $userId,
    array $permissions,
  ): void {
    $now = new DateTimeImmutable('2026-08-19T00:00:00+00:00');

    $organization = $entityManager->getReference(OrganizationRecord::class, $organizationId);

    $role = new OrganizationRoleRecord();
    $role->id = substr($userId, 0, 35) . 'r';
    $role->organization = $organization;
    $role->name = 'scoped_role_' . substr($userId, -4);
    $role->permissions = $permissions;
    $role->description = 'Functional-test-only role with a restricted permission set.';
    $role->isSystem = false;
    $role->createdAt = $now;
    $entityManager->persist($role);

    $member = new OrganizationMemberRecord();
    $member->id = substr($userId, 0, 35) . 'm';
    $member->organization = $organization;
    $member->userId = $userId;
    $member->isActive = true;
    $member->joinedAt = $now;
    $entityManager->persist($member);

    $roleAssignment = new OrganizationMemberRoleRecord();
    $roleAssignment->member = $member;
    $roleAssignment->role = $role;
    $roleAssignment->assignedAt = $now;
    $entityManager->persist($roleAssignment);
  }

  private function securityUser(string $userId): SecurityUser
  {
    return new SecurityUser(
      id: $userId,
      email: $userId . '@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
  }

  private function seedFullAccessOrganization(
    EntityManagerInterface $entityManager,
    string $organizationId,
    string $ownerUserId,
    DateTimeImmutable $now,
  ): OrganizationRecord {
    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Import Test ' . $organizationId;
    $organization->slug = 'import-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $role = new OrganizationRoleRecord();
    $role->id = substr($organizationId, 0, 35) . 'a';
    $role->organization = $organization;
    $role->name = 'full_access_role';
    $role->permissions = ['*'];
    $role->description = 'Functional-test-only role granting every permission.';
    $role->isSystem = false;
    $role->createdAt = $now;
    $entityManager->persist($role);

    $member = new OrganizationMemberRecord();
    $member->id = substr($organizationId, 0, 35) . 'b';
    $member->organization = $organization;
    $member->userId = $ownerUserId;
    $member->isActive = true;
    $member->joinedAt = $now;
    $entityManager->persist($member);

    $roleAssignment = new OrganizationMemberRoleRecord();
    $roleAssignment->member = $member;
    $roleAssignment->role = $role;
    $roleAssignment->assignedAt = $now;
    $entityManager->persist($roleAssignment);

    return $organization;
  }
}
