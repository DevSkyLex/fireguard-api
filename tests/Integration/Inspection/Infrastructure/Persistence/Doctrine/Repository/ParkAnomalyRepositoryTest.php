<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Exception\InvalidStorageTimeZoneException;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Inspection\Infrastructure\Persistence\Doctrine\Repository\ParkAnomalyRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test ParkAnomalyRepositoryTest.
 *
 * Exercises the shared predicates on PostgreSQL without reaching into Equipment persistence.
 *
 * @category Integration Tests
 */
#[CoversClass(ParkAnomalyRepository::class)]
final class ParkAnomalyRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '993e8400-e29b-41d4-a716-446655493001';

  private const string OTHER_ORGANIZATION_ID = '993e8400-e29b-41d4-a716-446655493002';

  private const string EQUIPMENT_A = '993e8400-e29b-41d4-a716-446655493010';

  private const string EQUIPMENT_B = '993e8400-e29b-41d4-a716-446655493011';

  private const string DRAFT_EQUIPMENT = '993e8400-e29b-41d4-a716-446655493012';

  private EntityManagerInterface $entityManager;

  private ParkAnomalyRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->repository = new ParkAnomalyRepository($entityManager, 'UTC');
    $this->seed();
  }

  #[Test]
  public function testCandidatesListCountAndSummarySharePublishedUnresolvedOrganizationPredicates(): void
  {
    self::assertSame([self::EQUIPMENT_A, self::EQUIPMENT_B], $this->repository->findCandidateEquipmentIds(self::ORGANIZATION_ID));
    $scope = [self::EQUIPMENT_A, self::EQUIPMENT_B, self::DRAFT_EQUIPMENT];
    $rows = $this->repository->list(self::ORGANIZATION_ID, $scope, new Pagination(0, 20), new Sorting('createdAt', SortDirection::DESC));
    self::assertCount(3, $rows);
    self::assertSame(3, $this->repository->count(self::ORGANIZATION_ID, $scope));
    $summary = $this->repository->summary(self::ORGANIZATION_ID, $scope);
    self::assertSame(3, $summary->openAnomalies);
    self::assertSame(['low' => 0, 'medium' => 1, 'high' => 1, 'critical' => 1], $summary->bySeverity);
    self::assertSame('993e8400-e29b-41d4-a716-446655493032', $rows[0]->id);
    self::assertSame('2026-10-06T10:00:00+00:00', $rows[0]->createdAt->format('c'));
  }

  #[Test]
  public function testResolvedEquipmentScopeIsAppliedBeforeSqlPaginationAndCounts(): void
  {
    $scope = [self::EQUIPMENT_A];
    $rows = $this->repository->list(self::ORGANIZATION_ID, $scope, new Pagination(1, 1), new Sorting('createdAt', SortDirection::DESC));
    self::assertCount(1, $rows);
    self::assertSame(self::EQUIPMENT_A, $rows[0]->equipmentId);
    self::assertSame('993e8400-e29b-41d4-a716-446655493030', $rows[0]->id);
    self::assertSame(2, $this->repository->count(self::ORGANIZATION_ID, $scope));
    self::assertSame(['low' => 0, 'medium' => 0, 'high' => 1, 'critical' => 1], $this->repository->summary(self::ORGANIZATION_ID, $scope)->bySeverity);
  }

  #[Test]
  public function testEmptyResolvedScopeNeverMeansAllEquipment(): void
  {
    self::assertSame([], $this->repository->list(self::ORGANIZATION_ID, [], new Pagination(), new Sorting('createdAt', SortDirection::DESC)));
    self::assertSame(0, $this->repository->count(self::ORGANIZATION_ID, []));
    $summary = $this->repository->summary(self::ORGANIZATION_ID, []);
    self::assertSame(0, $summary->openAnomalies);
    self::assertSame(['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0], $summary->bySeverity);
  }

  #[Test]
  public function testUnknownSortCannotInjectSqlAndUsesStableDefault(): void
  {
    $scope = [self::EQUIPMENT_A, self::EQUIPMENT_B];
    $rows = $this->repository->list(self::ORGANIZATION_ID, $scope, new Pagination(0, 1), new Sorting('createdAt; DROP TABLE inspections', SortDirection::DESC));
    self::assertCount(1, $rows);
    self::assertSame('993e8400-e29b-41d4-a716-446655493032', $rows[0]->id);
    self::assertSame(3, $this->repository->count(self::ORGANIZATION_ID, $scope));
  }

  #[Test]
  public function testTimestampsRespectConfiguredStorageTimeZone(): void
  {
    $repository = new ParkAnomalyRepository($this->entityManager, 'Europe/Paris');
    $rows = $repository->list(self::ORGANIZATION_ID, [self::EQUIPMENT_A], new Pagination(0, 1), new Sorting('createdAt', SortDirection::DESC));
    self::assertCount(1, $rows);
    self::assertSame('2026-10-06T10:00:00+02:00', $rows[0]->createdAt->format('c'));
    self::assertSame('2026-10-07T10:00:00+02:00', $rows[0]->dueAt?->format('c'));
  }

  #[Test]
  public function testInvalidStorageTimeZoneFailsExplicitly(): void
  {
    $this->expectException(InvalidStorageTimeZoneException::class);
    new ParkAnomalyRepository($this->entityManager, 'invalid-storage-zone');
  }

  /**
   * Method seed.
   */
  private function seed(): void
  {
    $organization = $this->organization(self::ORGANIZATION_ID, 'park-anomaly-repository');
    $foreignOrganization = $this->organization(self::OTHER_ORGANIZATION_ID, 'park-anomaly-repository-other');
    $publishedA = $this->inspection('993e8400-e29b-41d4-a716-446655493020', $organization, self::EQUIPMENT_A, 'published');
    $publishedB = $this->inspection('993e8400-e29b-41d4-a716-446655493021', $organization, self::EQUIPMENT_B, 'published');
    $draft = $this->inspection('993e8400-e29b-41d4-a716-446655493022', $organization, self::DRAFT_EQUIPMENT, 'draft');
    $foreign = $this->inspection('993e8400-e29b-41d4-a716-446655493023', $foreignOrganization, self::EQUIPMENT_A, 'published');
    $this->finding('993e8400-e29b-41d4-a716-446655493030', $publishedA, 'high', 'open');
    $this->finding('993e8400-e29b-41d4-a716-446655493031', $publishedA, 'critical', 'in_progress');
    $this->finding('993e8400-e29b-41d4-a716-446655493032', $publishedB, 'medium', 'open');
    $this->finding('993e8400-e29b-41d4-a716-446655493033', $publishedA, 'low', 'done');
    $this->finding('993e8400-e29b-41d4-a716-446655493034', $publishedA, 'low', 'cancelled');
    $this->finding('993e8400-e29b-41d4-a716-446655493035', $draft, 'critical', 'open');
    $this->finding('993e8400-e29b-41d4-a716-446655493036', $foreign, 'critical', 'open');
    $this->entityManager->flush();
  }

  /**
   * Method organization.
   */
  private function organization(string $id, string $slug): OrganizationRecord
  {
    $now = new DateTimeImmutable('2026-10-06T10:00:00+00:00');
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Park anomaly repository tests';
    $organization->slug = $slug;
    $organization->ownerUserId = '993e8400-e29b-41d4-a716-446655493009';
    $organization->createdByUserId = $organization->ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $this->entityManager->persist($organization);

    return $organization;
  }

  /**
   * Method inspection.
   */
  private function inspection(string $id, OrganizationRecord $organization, string $equipmentId, string $publicationState): InspectionRecord
  {
    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->equipmentId = $equipmentId;
    $inspection->recordStatus = $publicationState;
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Park inspection';
    $inspection->result = 'fail';
    $inspection->status = 'draft' === $publicationState ? 'draft' : 'closed';
    $inspection->performedAt = new DateTimeImmutable('2026-10-06T10:00:00+00:00');
    $inspection->createdAt = $inspection->performedAt;
    $inspection->updatedAt = $inspection->performedAt;
    $this->entityManager->persist($inspection);

    return $inspection;
  }

  /**
   * Method finding.
   */
  private function finding(string $id, InspectionRecord $inspection, string $severity, string $status): void
  {
    $finding = new NonConformityRecord();
    $finding->id = $id;
    $finding->inspection = $inspection;
    $finding->description = 'Scoped park finding';
    $finding->severity = $severity;
    $finding->status = $status;
    $finding->dueAt = new DateTimeImmutable('2026-10-07T10:00:00+00:00');
    $finding->createdAt = new DateTimeImmutable('2026-10-06T10:00:00+00:00');
    $finding->updatedAt = $finding->createdAt;
    $this->entityManager->persist($finding);
  }
}
