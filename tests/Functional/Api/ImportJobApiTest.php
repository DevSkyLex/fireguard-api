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
