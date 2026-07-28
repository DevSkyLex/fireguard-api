<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{
  InspectionOrganizationId,
  NonConformityId,
  NonConformityInspectionId,
  NonConformitySeverity,
  NonConformityStatus
};
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Repository\NonConformityRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use RuntimeException;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function array_sum;
use function ksort;

/**
 * Test NonConformityRepositoryStatisticsTest.
 *
 * Complements NonConformityRepositoryTest by exercising the aggregation and
 * filtering branches the round-trip test leaves untouched: the save() update
 * path, every optional severity / status / search predicate on both list query
 * builders, each resolveSortField() branch, and the overview / overdue /
 * active-at-date / period / per-day aggregations — including the PostgreSQL
 * timezone bucketing, which only a real database can evaluate.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NonConformityRepository::class)]
final class NonConformityRepositoryStatisticsTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '993e8400-e29b-41d4-a716-446655493001';

  private const string OTHER_ORGANIZATION_ID = '993e8400-e29b-41d4-a716-446655493002';

  private const string INSPECTION_ID = '993e8400-e29b-41d4-a716-446655493101';

  private const string FOREIGN_INSPECTION_ID = '993e8400-e29b-41d4-a716-446655493102';

  private const string CRITICAL_OPEN_ID = '993e8400-e29b-41d4-a716-446655493201';

  private const string HIGH_IN_PROGRESS_ID = '993e8400-e29b-41d4-a716-446655493202';

  private const string LOW_DONE_ID = '993e8400-e29b-41d4-a716-446655493203';

  private const string FOREIGN_ID = '993e8400-e29b-41d4-a716-446655493204';

  private const string OWNER_USER_ID = '993e8400-e29b-41d4-a716-446655499000';

  private const string OVERDUE_CUTOFF = '2026-03-05T00:00:00+00:00';

  private EntityManagerInterface $entityManager;

  private NonConformityRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new NonConformityRepository($this->entityManager);

    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'nc-statistics-org');
    $otherOrganization = $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'nc-statistics-org-b');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($otherOrganization);
    $this->entityManager->persist($this->createInspection(self::INSPECTION_ID, $organization));
    $this->entityManager->persist($this->createInspection(self::FOREIGN_INSPECTION_ID, $otherOrganization));
    $this->entityManager->flush();

    $this->seed();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveUpdatesAnExistingNonConformityInPlace(): void
  {
    $existing = $this->repository->findById(NonConformityId::fromString(self::CRITICAL_OPEN_ID));
    self::assertNotNull($existing);

    $existing->updateStatus(NonConformityStatus::DONE);
    $this->repository->save($existing);
    $this->entityManager->clear();

    $reloaded = $this->repository->findById(NonConformityId::fromString(self::CRITICAL_OPEN_ID));

    self::assertNotNull($reloaded);
    self::assertSame('done', $reloaded->status()->value);
    self::assertNotNull($reloaded->resolvedAt());
    // The update path must not create a second row for the same identifier.
    self::assertSame(3, $this->repository->countByInspectionId(
      NonConformityInspectionId::fromString(self::INSPECTION_ID),
    ));
  }

  #[Test]
  public function testCountOverviewAggregatesEveryStatusAndAppliesOptionalFilters(): void
  {
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    $overview = $this->repository->countOverviewByOrganizationId($organizationId, self::OVERDUE_CUTOFF);

    self::assertSame(3, $overview['total']);
    self::assertSame(1, $overview['open']);
    self::assertSame(1, $overview['in_progress']);
    self::assertSame(1, $overview['done']);
    self::assertSame(0, $overview['waived']);
    // Only the critical one carries a past due date while still being open.
    self::assertSame(1, $overview['overdue']);
    self::assertSame(1, $overview['critical_open']);

    $filtered = $this->repository->countOverviewByOrganizationId(
      $organizationId,
      self::OVERDUE_CUTOFF,
      severity: 'critical',
      status: 'open',
    );

    self::assertSame(1, $filtered['total']);
    self::assertSame(1, $filtered['open']);
    self::assertSame(0, $filtered['done']);
  }

  #[Test]
  public function testCountOverdueDefaultsToOpenStatusesAndHonoursExplicitFilters(): void
  {
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    self::assertSame(1, $this->repository->countOverdueByOrganizationId($organizationId, self::OVERDUE_CUTOFF));
    self::assertSame(1, $this->repository->countOverdueByOrganizationId(
      $organizationId,
      self::OVERDUE_CUTOFF,
      severity: 'critical',
      status: 'open',
    ));
    self::assertSame(0, $this->repository->countOverdueByOrganizationId(
      $organizationId,
      self::OVERDUE_CUTOFF,
      severity: 'low',
    ));
  }

  #[Test]
  public function testCountActiveAtDateExcludesRowsResolvedBeforeTheCutoff(): void
  {
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    // At the cutoff, the "done" row is already resolved and drops out.
    self::assertSame(2, $this->repository->countActiveByOrganizationIdAtDate($organizationId, '2026-03-04T00:00:00+00:00'));
    self::assertSame(3, $this->repository->countActiveByOrganizationIdAtDate($organizationId, '2026-03-03T00:00:00+00:00'));
    self::assertSame(1, $this->repository->countActiveByOrganizationIdAtDate(
      $organizationId,
      '2026-03-04T00:00:00+00:00',
      severity: 'critical',
      status: 'open',
    ));
  }

  #[Test]
  public function testCountOpenCriticalDefaultsToOpenStatusesAndHonoursAnExplicitStatus(): void
  {
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    self::assertSame(1, $this->repository->countOpenCriticalByOrganizationId($organizationId));
    self::assertSame(1, $this->repository->countOpenCriticalByOrganizationId($organizationId, 'open'));
    self::assertSame(0, $this->repository->countOpenCriticalByOrganizationId($organizationId, 'done'));
  }

  #[Test]
  public function testCountPeriodMetricsSplitsOpenedResolvedAndActiveAtStart(): void
  {
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    $metrics = $this->repository->countPeriodMetricsByOrganizationId(
      $organizationId,
      '2026-03-01T00:00:00+00:00',
      '2026-03-31T23:59:59+00:00',
      '2026-03-04T00:00:00+00:00',
    );

    self::assertSame(3, $metrics['opened']);
    self::assertSame(1, $metrics['resolved']);
    self::assertSame(2, $metrics['activeAtStart']);

    $filtered = $this->repository->countPeriodMetricsByOrganizationId(
      $organizationId,
      '2026-03-01T00:00:00+00:00',
      '2026-03-31T23:59:59+00:00',
      '2026-03-04T00:00:00+00:00',
      severity: 'low',
      status: 'done',
    );

    self::assertSame(1, $filtered['opened']);
    self::assertSame(1, $filtered['resolved']);
  }

  #[Test]
  public function testCountByCreatedDayBucketsInTheRequestedTimeZone(): void
  {
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    $buckets = $this->repository->countByCreatedDayForOrganizationId(
      $organizationId,
      '2026-02-28T00:00:00+00:00',
      '2026-03-31T23:59:59+00:00',
      'UTC',
    );

    self::assertSame(3, array_sum($buckets));
    self::assertArrayHasKey('2026-03-01', $buckets);
    self::assertSame(2, $buckets['2026-03-01']);

    // With no explicit time zone the bucketing falls back to the lower bound's
    // own offset rather than failing.
    $offsetBuckets = $this->repository->countByCreatedDayForOrganizationId(
      $organizationId,
      '2026-02-28T00:00:00+01:00',
      '2026-03-31T23:59:59+01:00',
    );

    self::assertSame(3, array_sum($offsetBuckets));

    $filtered = $this->repository->countByCreatedDayForOrganizationId(
      $organizationId,
      '2026-02-28T00:00:00+00:00',
      '2026-03-31T23:59:59+00:00',
      'UTC',
      severity: 'critical',
      status: 'open',
    );

    self::assertSame(['2026-03-01' => 1], $filtered);
  }

  #[Test]
  public function testCountByResolvedDayOnlyCountsResolvedRows(): void
  {
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    $buckets = $this->repository->countByResolvedDayForOrganizationId(
      $organizationId,
      '2026-02-28T00:00:00+00:00',
      '2026-03-31T23:59:59+00:00',
      'UTC',
    );

    self::assertSame(['2026-03-03' => 1], $buckets);

    self::assertSame([], $this->repository->countByResolvedDayForOrganizationId(
      $organizationId,
      '2026-02-28T00:00:00+00:00',
      '2026-03-31T23:59:59+00:00',
      'UTC',
      severity: 'critical',
    ));

    self::assertSame(['2026-03-03' => 1], $this->repository->countByResolvedDayForOrganizationId(
      $organizationId,
      '2026-02-28T00:00:00+00:00',
      '2026-03-31T23:59:59+00:00',
      null,
      severity: 'low',
      status: 'done',
    ));
  }

  #[Test]
  public function testFindByInspectionIdAppliesEveryOptionalPredicate(): void
  {
    $inspectionId = NonConformityInspectionId::fromString(self::INSPECTION_ID);

    self::assertSame(
      [self::CRITICAL_OPEN_ID],
      $this->identifiers($this->repository->findByInspectionId($inspectionId, severity: 'critical')),
    );
    self::assertSame(1, $this->repository->countByInspectionId($inspectionId, severity: 'critical'));

    self::assertSame(
      [self::HIGH_IN_PROGRESS_ID],
      $this->identifiers($this->repository->findByInspectionId($inspectionId, status: 'in_progress')),
    );
    self::assertSame(1, $this->repository->countByInspectionId($inspectionId, status: 'in_progress'));

    // The search term carries LIKE metacharacters that must be escaped.
    self::assertSame(
      [self::CRITICAL_OPEN_ID],
      $this->identifiers($this->repository->findByInspectionId($inspectionId, search: '100% survey_A')),
    );
    self::assertSame(1, $this->repository->countByInspectionId($inspectionId, search: '100% survey_A'));
    self::assertSame(0, $this->repository->countByInspectionId($inspectionId, search: '100_ survey%A'));
  }

  #[Test]
  public function testFindByOrganizationIdSortsPaginatesAndFilters(): void
  {
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    foreach (['severity', 'status', 'dueAt', 'createdAt', 'unknownField'] as $field) {
      $sorted = $this->repository->findByOrganizationId(
        $organizationId,
        sorting: new Sorting($field, SortDirection::ASC),
      );

      self::assertCount(3, $sorted, 'Sorting by ' . $field . ' must not change the result set.');
    }

    $firstPage = $this->repository->findByOrganizationId(
      $organizationId,
      sorting: new Sorting('severity', SortDirection::ASC),
      limit: 1,
    );
    self::assertCount(1, $firstPage);

    $secondPage = $this->repository->findByOrganizationId(
      $organizationId,
      sorting: new Sorting('severity', SortDirection::ASC),
      limit: 1,
      offset: 1,
    );
    self::assertCount(1, $secondPage);
    self::assertNotSame($this->identifiers($firstPage), $this->identifiers($secondPage));

    self::assertSame(
      [self::LOW_DONE_ID],
      $this->identifiers($this->repository->findByOrganizationId($organizationId, severity: 'low', status: 'done')),
    );
    self::assertSame(1, $this->repository->countByOrganizationId($organizationId, severity: 'low', status: 'done'));
    self::assertSame(
      [self::CRITICAL_OPEN_ID],
      $this->identifiers($this->repository->findByOrganizationId($organizationId, search: '100% survey_A')),
    );
    self::assertSame(1, $this->repository->countByOrganizationId($organizationId, search: '100% survey_A'));
  }

  #[Test]
  public function testStatusAndSeverityGroupingsAreScopedToTheOrganization(): void
  {
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    self::assertSame(
      ['done' => 1, 'in_progress' => 1, 'open' => 1],
      $this->sorted($this->repository->countByStatusForOrganizationId($organizationId)),
    );
    self::assertSame(
      ['critical' => 1, 'high' => 1, 'low' => 1],
      $this->sorted($this->repository->countBySeverityForOrganizationId($organizationId)),
    );
    self::assertSame(
      ['medium' => 1],
      $this->repository->countBySeverityForOrganizationId(
        InspectionOrganizationId::fromString(self::OTHER_ORGANIZATION_ID),
      ),
    );
  }

  #[Test]
  public function testAMisconfiguredStorageTimeZoneFailsLoudly(): void
  {
    $repository = new NonConformityRepository($this->entityManager, 'Not/AZone');

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Invalid DATABASE_STORAGE_TIMEZONE configuration.');

    $repository->countOverdueByOrganizationId(
      InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      self::OVERDUE_CUTOFF,
    );
  }

  /**
   * @param array<string, int> $counts
   *
   * @return array<string, int>
   */
  private function sorted(array $counts): array
  {
    ksort($counts);

    return $counts;
  }

  /**
   * @param list<NonConformity> $nonConformities
   *
   * @return list<string>
   */
  private function identifiers(array $nonConformities): array
  {
    return array_map(
      static fn (NonConformity $nonConformity): string => (string) $nonConformity->id(),
      $nonConformities,
    );
  }

  private function seed(): void
  {
    $this->repository->save(NonConformity::reconstitute(
      id: NonConformityId::fromString(self::CRITICAL_OPEN_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Blocked exit — 100% survey_A',
      severity: NonConformitySeverity::CRITICAL,
      status: NonConformityStatus::OPEN,
      createdAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
    ));
    $this->repository->save(NonConformity::reconstitute(
      id: NonConformityId::fromString(self::HIGH_IN_PROGRESS_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Missing signage',
      severity: NonConformitySeverity::HIGH,
      status: NonConformityStatus::IN_PROGRESS,
      createdAt: new DateTimeImmutable('2026-03-02T23:30:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-02T23:30:00+00:00'),
    ));
    $this->repository->save(NonConformity::reconstitute(
      id: NonConformityId::fromString(self::LOW_DONE_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Faded floor marking',
      severity: NonConformitySeverity::LOW,
      status: NonConformityStatus::DONE,
      createdAt: new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-03T08:00:00+00:00'),
      resolvedAt: new DateTimeImmutable('2026-03-03T08:00:00+00:00'),
      notes: 'Repainted',
    ));
    // Belongs to another organization: never counted in the scoped aggregates.
    $this->repository->save(NonConformity::reconstitute(
      id: NonConformityId::fromString(self::FOREIGN_ID),
      inspectionId: NonConformityInspectionId::fromString(self::FOREIGN_INSPECTION_ID),
      description: 'Unrelated defect',
      severity: NonConformitySeverity::MEDIUM,
      status: NonConformityStatus::OPEN,
      createdAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-01T09:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
    ));
    $this->entityManager->clear();
  }

  private function createInspection(string $id, OrganizationRecord $organization): InspectionRecord
  {
    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->equipmentId = '993e8400-e29b-41d4-a716-446655498888';
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Jane Doe';
    $inspection->result = 'pass';
    $inspection->status = 'draft';
    $inspection->performedAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
    $inspection->createdAt = $inspection->performedAt;
    $inspection->updatedAt = $inspection->performedAt;

    return $inspection;
  }

  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Non-Conformity Statistics ' . $slug;
    $organization->slug = $slug;
    $organization->ownerUserId = self::OWNER_USER_ID;
    $organization->createdByUserId = self::OWNER_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;

    return $organization;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $inspectionIds = [self::INSPECTION_ID, self::FOREIGN_INSPECTION_ID];
    $connection->executeStatement(
      'DELETE FROM non_conformities WHERE inspection_id IN (:inspectionIds)',
      ['inspectionIds' => $inspectionIds],
      ['inspectionIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM inspections WHERE id IN (:inspectionIds)',
      ['inspectionIds' => $inspectionIds],
      ['inspectionIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
