<?php

declare(strict_types=1);

namespace Tests\Integration\Import\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportRowError, ImportStatus};
use Import\Infrastructure\Persistence\Doctrine\Repository\ImportJobRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test ImportJobRepository.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportJobRepository::class)]
final class ImportJobRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655440001';

  private const string OTHER_ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655440002';

  private const string ACTOR_ID = '770e8400-e29b-41d4-a716-446655440009';

  private const string SAVE_JOB_ID = '770e8400-e29b-41d4-a716-4466554400a0';

  private const string JOB_A = '770e8400-e29b-41d4-a716-4466554400a1';

  private const string JOB_B = '770e8400-e29b-41d4-a716-4466554400a2';

  private const string JOB_C = '770e8400-e29b-41d4-a716-4466554400a3';

  private const string OTHER_JOB = '770e8400-e29b-41d4-a716-4466554400a9';

  private const string JOB_TIE_LOW = '770e8400-e29b-41d4-a716-4466554400b1';

  private const string JOB_TIE_HIGH = '770e8400-e29b-41d4-a716-4466554400b2';

  private const string CLAIM_PENDING = '770e8400-e29b-41d4-a716-4466554400c1';

  private const string CLAIM_COMPLETED = '770e8400-e29b-41d4-a716-4466554400c2';

  private const string CLAIM_FAILED = '770e8400-e29b-41d4-a716-4466554400c3';

  private const string ABSENT_JOB = '770e8400-e29b-41d4-a716-4466554400cf';

  private EntityManagerInterface $entityManager;

  private ImportJobRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var ImportJobRepository $repository */
    $repository = static::getContainer()->get(ImportJobRepository::class);
    $this->repository = $repository;

    $this->createOrganization(self::ORGANIZATION_ID, 'import-repo-test-primary');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'import-repo-test-secondary');
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveInsertsAPendingJobThatFindByIdReconstitutes(): void
  {
    $job = ImportJob::create(
      ImportJobId::fromString(self::SAVE_JOB_ID),
      self::ORGANIZATION_ID,
      ImportKind::EQUIPMENT,
      'imports/save.csv',
      'equipment-import.csv',
      self::ACTOR_ID,
    );
    $this->repository->save($job);
    $this->entityManager->clear();

    $found = $this->repository->findById(ImportJobId::fromString(self::SAVE_JOB_ID));

    self::assertInstanceOf(ImportJob::class, $found);
    self::assertTrue($found->id()->equals($job->id()));
    self::assertSame(self::ORGANIZATION_ID, $found->organizationId());
    self::assertSame(ImportKind::EQUIPMENT, $found->kind());
    self::assertSame(ImportStatus::PENDING, $found->status());
    self::assertSame('imports/save.csv', $found->storagePath());
    self::assertSame('equipment-import.csv', $found->originalFilename());
    self::assertSame(self::ACTOR_ID, $found->createdBy());
    self::assertNull($found->totalRows());
    self::assertSame(0, $found->processedRows());
    self::assertSame([], $found->errorReport());
    self::assertNull($found->startedAt());
    self::assertNull($found->completedAt());
  }

  #[Test]
  public function testFindByIdReturnsNullWhenTheJobDoesNotExist(): void
  {
    self::assertNull($this->repository->findById(ImportJobId::fromString(self::ABSENT_JOB)));
  }

  #[Test]
  public function testSaveUpdatesTheExistingRecordAndRoundTripsTheErrorReport(): void
  {
    $now = new DateTimeImmutable('2026-03-01T09:00:00+00:00');
    $job = ImportJob::create(
      ImportJobId::fromString(self::SAVE_JOB_ID),
      self::ORGANIZATION_ID,
      ImportKind::FACILITY,
      'imports/update.csv',
      'facilities.csv',
      self::ACTOR_ID,
    );
    $this->repository->save($job);
    $this->entityManager->clear();

    $job->markProcessing($now);
    $job->setTotalRows(3);
    $job->recordRowSuccess();
    // First failure carries no column (exercises the mapper's null-column branch).
    $job->recordRowError(new ImportRowError(1, 'invalid', 'Malformed row'));
    // Second failure names the offending column.
    $job->recordRowError(new ImportRowError(2, 'quota_exceeded', 'Plan limit reached', 'siret'));
    $job->complete($now);
    $this->repository->save($job);
    $this->entityManager->clear();

    $found = $this->repository->findById(ImportJobId::fromString(self::SAVE_JOB_ID));

    self::assertInstanceOf(ImportJob::class, $found);
    self::assertSame(ImportStatus::COMPLETED, $found->status());
    self::assertSame(3, $found->totalRows());
    self::assertSame(3, $found->processedRows());
    self::assertSame(1, $found->successfulRows());
    self::assertSame(2, $found->failedRows());
    self::assertNotNull($found->startedAt());
    self::assertNotNull($found->completedAt());

    self::assertCount(2, $found->errorReport());
    self::assertSame(1, $found->errorReport()[0]->rowNumber);
    self::assertSame('invalid', $found->errorReport()[0]->code);
    self::assertSame('Malformed row', $found->errorReport()[0]->message);
    self::assertNull($found->errorReport()[0]->column);
    self::assertSame('quota_exceeded', $found->errorReport()[1]->code);
    self::assertSame('siret', $found->errorReport()[1]->column);
  }

  #[Test]
  public function testListByOrganizationReturnsScopedJobsNewestFirst(): void
  {
    $this->saveJob(self::JOB_A, ImportKind::EQUIPMENT, ImportStatus::COMPLETED, new DateTimeImmutable('2026-04-01T08:00:00+00:00'));
    $this->saveJob(self::JOB_B, ImportKind::FACILITY, ImportStatus::PENDING, new DateTimeImmutable('2026-04-02T08:00:00+00:00'));
    $this->saveJob(self::JOB_C, ImportKind::EQUIPMENT, ImportStatus::PROCESSING, new DateTimeImmutable('2026-04-03T08:00:00+00:00'));
    // Belongs to another organization: must be excluded from the scoped listing.
    $this->saveJob(self::OTHER_JOB, ImportKind::EQUIPMENT, ImportStatus::PENDING, new DateTimeImmutable('2026-04-04T08:00:00+00:00'), self::OTHER_ORGANIZATION_ID);

    $jobs = $this->repository->listByOrganization(self::ORGANIZATION_ID, null, 10, 0);

    self::assertCount(3, $jobs);
    self::assertSame([self::JOB_C, self::JOB_B, self::JOB_A], $this->idsOf($jobs));
  }

  #[Test]
  public function testListByOrganizationBreaksCreatedAtTiesByIdDescending(): void
  {
    $sharedCreatedAt = new DateTimeImmutable('2026-05-01T10:00:00+00:00');
    $this->saveJob(self::JOB_TIE_LOW, ImportKind::EQUIPMENT, ImportStatus::PENDING, $sharedCreatedAt);
    $this->saveJob(self::JOB_TIE_HIGH, ImportKind::EQUIPMENT, ImportStatus::PENDING, $sharedCreatedAt);

    $jobs = $this->repository->listByOrganization(self::ORGANIZATION_ID, null, 10, 0);

    self::assertSame([self::JOB_TIE_HIGH, self::JOB_TIE_LOW], $this->idsOf($jobs));
  }

  #[Test]
  public function testListByOrganizationFiltersByKindAndPaginates(): void
  {
    $this->saveJob(self::JOB_A, ImportKind::EQUIPMENT, ImportStatus::COMPLETED, new DateTimeImmutable('2026-04-01T08:00:00+00:00'));
    $this->saveJob(self::JOB_B, ImportKind::FACILITY, ImportStatus::PENDING, new DateTimeImmutable('2026-04-02T08:00:00+00:00'));
    $this->saveJob(self::JOB_C, ImportKind::EQUIPMENT, ImportStatus::PROCESSING, new DateTimeImmutable('2026-04-03T08:00:00+00:00'));

    $equipment = $this->repository->listByOrganization(self::ORGANIZATION_ID, ImportKind::EQUIPMENT, 10, 0);
    self::assertSame([self::JOB_C, self::JOB_A], $this->idsOf($equipment));

    $firstPage = $this->repository->listByOrganization(self::ORGANIZATION_ID, null, 2, 0);
    self::assertSame([self::JOB_C, self::JOB_B], $this->idsOf($firstPage));

    $secondPage = $this->repository->listByOrganization(self::ORGANIZATION_ID, null, 2, 2);
    self::assertSame([self::JOB_A], $this->idsOf($secondPage));
  }

  #[Test]
  public function testCountByOrganizationHonoursTheKindFilterAndScope(): void
  {
    $this->saveJob(self::JOB_A, ImportKind::EQUIPMENT, ImportStatus::COMPLETED, new DateTimeImmutable('2026-04-01T08:00:00+00:00'));
    $this->saveJob(self::JOB_B, ImportKind::FACILITY, ImportStatus::PENDING, new DateTimeImmutable('2026-04-02T08:00:00+00:00'));
    $this->saveJob(self::JOB_C, ImportKind::EQUIPMENT, ImportStatus::PROCESSING, new DateTimeImmutable('2026-04-03T08:00:00+00:00'));
    $this->saveJob(self::OTHER_JOB, ImportKind::EQUIPMENT, ImportStatus::PENDING, new DateTimeImmutable('2026-04-04T08:00:00+00:00'), self::OTHER_ORGANIZATION_ID);

    self::assertSame(3, $this->repository->countByOrganization(self::ORGANIZATION_ID, null));
    self::assertSame(2, $this->repository->countByOrganization(self::ORGANIZATION_ID, ImportKind::EQUIPMENT));
    self::assertSame(1, $this->repository->countByOrganization(self::ORGANIZATION_ID, ImportKind::FACILITY));
    // Scoped to the other organization: one equipment job, zero facility jobs.
    self::assertSame(1, $this->repository->countByOrganization(self::OTHER_ORGANIZATION_ID, null));
    self::assertSame(0, $this->repository->countByOrganization(self::OTHER_ORGANIZATION_ID, ImportKind::FACILITY));
  }

  #[Test]
  public function testClaimTransitionsPendingIsIdempotentForProcessingAndRejectsTerminal(): void
  {
    $this->saveJob(self::CLAIM_PENDING, ImportKind::EQUIPMENT, ImportStatus::PENDING, new DateTimeImmutable('2026-06-01T06:00:00+00:00'));
    $this->saveJob(
      self::CLAIM_COMPLETED,
      ImportKind::EQUIPMENT,
      ImportStatus::COMPLETED,
      new DateTimeImmutable('2026-06-01T06:00:00+00:00'),
      completedAt: new DateTimeImmutable('2026-06-01T06:30:00+00:00'),
    );
    $this->saveJob(
      self::CLAIM_FAILED,
      ImportKind::FACILITY,
      ImportStatus::FAILED,
      new DateTimeImmutable('2026-06-01T06:00:00+00:00'),
      jobError: 'Unreadable upload',
      completedAt: new DateTimeImmutable('2026-06-01T06:30:00+00:00'),
    );

    // A pending job is claimed: it becomes processing and gains a started_at.
    self::assertTrue($this->repository->claim(ImportJobId::fromString(self::CLAIM_PENDING)));
    $this->entityManager->clear();
    $claimed = $this->repository->findById(ImportJobId::fromString(self::CLAIM_PENDING));
    self::assertInstanceOf(ImportJob::class, $claimed);
    self::assertSame(ImportStatus::PROCESSING, $claimed->status());
    self::assertNotNull($claimed->startedAt());
    $firstStartedAt = $claimed->startedAt();

    // Re-claiming an already-processing job is a safe no-op: still true, started_at preserved (COALESCE).
    self::assertTrue($this->repository->claim(ImportJobId::fromString(self::CLAIM_PENDING)));
    $this->entityManager->clear();
    $reclaimed = $this->repository->findById(ImportJobId::fromString(self::CLAIM_PENDING));
    self::assertInstanceOf(ImportJob::class, $reclaimed);
    self::assertSame(ImportStatus::PROCESSING, $reclaimed->status());
    self::assertEquals($firstStartedAt, $reclaimed->startedAt());

    // Terminal jobs are never reclaimed.
    self::assertFalse($this->repository->claim(ImportJobId::fromString(self::CLAIM_COMPLETED)));
    self::assertFalse($this->repository->claim(ImportJobId::fromString(self::CLAIM_FAILED)));

    // An unknown identifier claims nothing.
    self::assertFalse($this->repository->claim(ImportJobId::fromString(self::ABSENT_JOB)));
  }

  /**
   * Saves an import job in a controlled state through the repository under test.
   *
   * @param string $id the import job identifier
   * @param ImportKind $kind the provisioned resource kind
   * @param ImportStatus $status the lifecycle status
   * @param DateTimeImmutable $createdAt the creation timestamp used for ordering
   * @param string $organizationId the owning organization identifier
   * @param ?string $jobError the catastrophic failure reason, when failed
   * @param ?DateTimeImmutable $completedAt the terminal timestamp, when terminal
   */
  private function saveJob(
    string $id,
    ImportKind $kind,
    ImportStatus $status,
    DateTimeImmutable $createdAt,
    string $organizationId = self::ORGANIZATION_ID,
    ?string $jobError = null,
    ?DateTimeImmutable $completedAt = null,
  ): void {
    $job = ImportJob::reconstitute(
      id: ImportJobId::fromString($id),
      organizationId: $organizationId,
      kind: $kind,
      status: $status,
      storagePath: 'imports/' . $id . '.csv',
      originalFilename: $kind->value . '.csv',
      createdBy: self::ACTOR_ID,
      createdAt: $createdAt,
      updatedAt: $createdAt,
      totalRows: null,
      processedRows: 0,
      successfulRows: 0,
      failedRows: 0,
      errorReport: [],
      jobError: $jobError,
      startedAt: null,
      completedAt: $completedAt,
    );

    $this->repository->save($job);
    $this->entityManager->clear();
  }

  /**
   * Extracts the string identifiers of a list of import jobs, in order.
   *
   * @param list<ImportJob> $jobs the import jobs
   *
   * @return list<string> the string identifiers
   */
  private function idsOf(array $jobs): array
  {
    return array_map(static fn (ImportJob $job): string => (string) $job->id(), $jobs);
  }

  private function createOrganization(string $id, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Import Repository Test ' . $slug;
    $organization->slug = $slug;
    $organization->ownerUserId = self::ACTOR_ID;
    $organization->createdByUserId = self::ACTOR_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM import_jobs WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM import_jobs WHERE organization_id = :organizationId',
      ['organizationId' => self::OTHER_ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::OTHER_ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
