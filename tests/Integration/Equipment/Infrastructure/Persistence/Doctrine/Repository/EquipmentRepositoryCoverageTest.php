<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentFacilityId, EquipmentId, EquipmentOrganizationId, EquipmentStatus, EquipmentType};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Infrastructure\Persistence\Doctrine\Repository\EquipmentRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test EquipmentRepositoryCoverageTest.
 *
 * Complements EquipmentRepositoryTest by exercising the branches that the
 * happy-path round-trip test leaves uncovered: the save() upsert-update arm,
 * the duplicate serial-number violation mapping, every list filter/search and
 * sort match arm, the type histogram, the created-day bucketing (with and
 * without an explicit bucket time zone) and the overview counts narrowed by
 * type/status.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentRepository::class)]
final class EquipmentRepositoryCoverageTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-4466554e0001';

  private const string FACILITY_A = '770e8400-e29b-41d4-a716-4466554e00a1';

  private const string FACILITY_B = '770e8400-e29b-41d4-a716-4466554e00a2';

  private EntityManagerInterface $entityManager;

  private EquipmentRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var EquipmentRepository $repository */
    $repository = static::getContainer()->get(EquipmentRepository::class);
    $this->repository = $repository;

    $this->createOrganization();
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
  public function testSaveUpdatesExistingRecordInPlace(): void
  {
    $id = '770e8400-e29b-41d4-a716-4466554e0010';

    $this->repository->save(Equipment::reconstitute(
      id: EquipmentId::fromString($id),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
      status: EquipmentStatus::IN_STOCK,
      createdAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      subType: 'foam',
      brand: 'Acme',
      model: 'M1',
      serialNumber: 'SN-UPSERT',
    ));
    $this->entityManager->clear();

    // Re-saving the same identifier must take the update arm: mutating fields
    // in place, keeping the original createdAt, applying the new updatedAt.
    $this->repository->save(Equipment::reconstitute(
      id: EquipmentId::fromString($id),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      type: EquipmentType::SMOKE_DETECTOR,
      status: EquipmentStatus::OPERATIONAL,
      createdAt: new DateTimeImmutable('2026-06-06T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-05T00:00:00+00:00'),
      facilityId: EquipmentFacilityId::fromString(self::FACILITY_A),
      subType: 'ion',
      brand: 'Beta',
      model: 'M2',
      serialNumber: 'SN-UPSERT-2',
    ));
    $this->entityManager->clear();

    $found = $this->repository->findById(EquipmentId::fromString($id));

    self::assertInstanceOf(Equipment::class, $found);
    self::assertSame(EquipmentType::SMOKE_DETECTOR, $found->type());
    self::assertSame(EquipmentStatus::OPERATIONAL, $found->status());
    self::assertSame('Beta', $found->brand());
    self::assertSame('M2', $found->model());
    self::assertSame('ion', $found->subType());
    self::assertSame('SN-UPSERT-2', $found->serialNumber());
    self::assertInstanceOf(EquipmentFacilityId::class, $found->facilityId());
    self::assertSame(self::FACILITY_A, (string) $found->facilityId());
    // createdAt preserved from the original insert, updatedAt advanced.
    self::assertSame('2026-02-01', $found->createdAt()->format('Y-m-d'));
    self::assertSame('2026-02-05', $found->updatedAt()->format('Y-m-d'));
  }

  #[Test]
  public function testSaveThrowsWhenSerialNumberDuplicatedWithinOrganization(): void
  {
    $this->repository->save(Equipment::reconstitute(
      id: EquipmentId::fromString('770e8400-e29b-41d4-a716-4466554e0020'),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
      status: EquipmentStatus::IN_STOCK,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      serialNumber: 'SN-DUPLICATE',
    ));
    $this->entityManager->clear();

    $this->expectException(EquipmentSerialNumberAlreadyExistsException::class);

    $this->repository->save(Equipment::reconstitute(
      id: EquipmentId::fromString('770e8400-e29b-41d4-a716-4466554e0021'),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      type: EquipmentType::SMOKE_DETECTOR,
      status: EquipmentStatus::IN_STOCK,
      createdAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
      serialNumber: 'SN-DUPLICATE',
    ));
  }

  #[Test]
  public function testFindByOrganizationIdAppliesEveryFilter(): void
  {
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0030', type: 'fire_extinguisher', status: 'operational', serialNumber: 'SN-F1', brand: 'Acme', model: 'M1', subType: 'foam', facilityId: self::FACILITY_A, locationLabel: 'Zone A');
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0031', type: 'smoke_detector', status: 'in_stock', serialNumber: 'SN-F2', brand: 'Beta', model: 'M2', subType: 'ion', facilityId: self::FACILITY_B, locationLabel: 'Zone B');
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0032', type: 'fire_extinguisher', status: 'under_maintenance', serialNumber: 'SN-F3', brand: 'Acme', model: 'M3', subType: 'powder', facilityId: self::FACILITY_A, locationLabel: 'Zone C');
    // Draft record: must never surface through the list/count query builder.
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0033', type: 'fire_extinguisher', status: 'operational', recordStatus: 'draft', serialNumber: 'SN-F4', brand: 'Acme');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $orgId = EquipmentOrganizationId::fromString(self::ORGANIZATION_ID);

    self::assertSame([
      '770e8400-e29b-41d4-a716-4466554e0030',
      '770e8400-e29b-41d4-a716-4466554e0032',
    ], $this->listIds($this->repository->findByOrganizationId($orgId, type: 'fire_extinguisher')));
    self::assertSame(2, $this->repository->countByOrganizationId($orgId, type: 'fire_extinguisher'));

    self::assertSame(['770e8400-e29b-41d4-a716-4466554e0030'], $this->listIds($this->repository->findByOrganizationId($orgId, status: 'operational')));
    self::assertSame(['770e8400-e29b-41d4-a716-4466554e0031'], $this->listIds($this->repository->findByOrganizationId($orgId, facilityId: self::FACILITY_B)));
    self::assertSame(2, $this->repository->countByOrganizationId($orgId, brand: 'Acme'));
    self::assertSame(['770e8400-e29b-41d4-a716-4466554e0031'], $this->listIds($this->repository->findByOrganizationId($orgId, model: 'M2')));
    self::assertSame(['770e8400-e29b-41d4-a716-4466554e0032'], $this->listIds($this->repository->findByOrganizationId($orgId, subType: 'powder')));

    // Free-text search matches case-insensitively across the searchable columns
    // (here the brand). The draft with brand "Acme" stays excluded.
    self::assertSame(['770e8400-e29b-41d4-a716-4466554e0031'], $this->listIds($this->repository->findByOrganizationId($orgId, search: 'beta')));
    self::assertSame(1, $this->repository->countByOrganizationId($orgId, search: 'beta'));
  }

  #[Test]
  public function testFindByOrganizationIdHonoursSortFieldAndDirection(): void
  {
    // camera / Alpha, day 1 — vs — hydrant / Zulu, day 2.
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0040', type: 'camera', status: 'operational', serialNumber: 'SN-S1', brand: 'Alpha', model: 'AA', createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0041', type: 'hydrant', status: 'in_stock', serialNumber: 'SN-S2', brand: 'Zulu', model: 'ZZ', createdAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $orgId = EquipmentOrganizationId::fromString(self::ORGANIZATION_ID);

    // brand DESC → Zulu before Alpha.
    self::assertSame([
      '770e8400-e29b-41d4-a716-4466554e0041',
      '770e8400-e29b-41d4-a716-4466554e0040',
    ], $this->listIds($this->repository->findByOrganizationId($orgId, sorting: new Sorting('brand', SortDirection::DESC))));

    // type ASC → camera before hydrant.
    self::assertSame([
      '770e8400-e29b-41d4-a716-4466554e0040',
      '770e8400-e29b-41d4-a716-4466554e0041',
    ], $this->listIds($this->repository->findByOrganizationId($orgId, sorting: new Sorting('type', SortDirection::ASC))));

    // updatedAt DESC → the later record first.
    self::assertSame([
      '770e8400-e29b-41d4-a716-4466554e0041',
      '770e8400-e29b-41d4-a716-4466554e0040',
    ], $this->listIds($this->repository->findByOrganizationId($orgId, sorting: new Sorting('updatedAt', SortDirection::DESC))));

    // status / model arms are exercised for coverage (ordering not asserted).
    self::assertCount(2, $this->repository->findByOrganizationId($orgId, sorting: new Sorting('status', SortDirection::ASC)));
    self::assertCount(2, $this->repository->findByOrganizationId($orgId, sorting: new Sorting('model', SortDirection::DESC)));
  }

  #[Test]
  public function testCountByTypeForOrganizationId(): void
  {
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0050', type: 'fire_extinguisher', serialNumber: 'SN-T1');
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0051', type: 'fire_extinguisher', serialNumber: 'SN-T2');
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0052', type: 'smoke_detector', serialNumber: 'SN-T3');
    // Draft excluded from the type histogram.
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0053', type: 'smoke_detector', recordStatus: 'draft', serialNumber: 'SN-T4');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $counts = $this->repository->countByTypeForOrganizationId(
      EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
    );

    self::assertSame(2, $counts['fire_extinguisher'] ?? 0);
    self::assertSame(1, $counts['smoke_detector'] ?? 0);
  }

  #[Test]
  public function testCountByCreatedDayForOrganizationIdBucketsAndFilters(): void
  {
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0060', type: 'fire_extinguisher', serialNumber: 'SN-D1', createdAt: new DateTimeImmutable('2026-03-01T10:00:00+00:00'));
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0061', type: 'fire_extinguisher', serialNumber: 'SN-D2', createdAt: new DateTimeImmutable('2026-03-01T20:00:00+00:00'));
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0062', type: 'fire_extinguisher', serialNumber: 'SN-D3', createdAt: new DateTimeImmutable('2026-03-02T05:00:00+00:00'));
    // Different type on an existing bucket: excluded once the type filter is set.
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0063', type: 'smoke_detector', serialNumber: 'SN-D4', createdAt: new DateTimeImmutable('2026-03-01T06:00:00+00:00'));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $orgId = EquipmentOrganizationId::fromString(self::ORGANIZATION_ID);

    // Default bucket time zone derived from the lower-bound offset (UTC).
    self::assertSame([
      '2026-03-01' => 3,
      '2026-03-02' => 1,
    ], $this->repository->countByCreatedDayForOrganizationId($orgId, '2026-03-01T00:00:00+00:00', '2026-03-03T00:00:00+00:00'));

    // Explicit bucket time zone plus the type filter arm.
    self::assertSame([
      '2026-03-01' => 2,
      '2026-03-02' => 1,
    ], $this->repository->countByCreatedDayForOrganizationId($orgId, '2026-03-01T00:00:00+00:00', '2026-03-03T00:00:00+00:00', 'UTC', 'fire_extinguisher'));
  }

  #[Test]
  public function testCountOverviewByOrganizationIdWithTypeAndStatusFilters(): void
  {
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0070', type: 'fire_extinguisher', status: 'operational', serialNumber: 'SN-O1');
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0071', type: 'fire_extinguisher', status: 'in_stock', serialNumber: 'SN-O2');
    $this->persistRecord('770e8400-e29b-41d4-a716-4466554e0072', type: 'smoke_detector', status: 'operational', serialNumber: 'SN-O3');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $orgId = EquipmentOrganizationId::fromString(self::ORGANIZATION_ID);

    $byType = $this->repository->countOverviewByOrganizationId($orgId, 'fire_extinguisher');
    self::assertSame(2, $byType['total']);
    self::assertSame(1, $byType['operational']);
    self::assertSame(1, $byType['in_stock']);

    $byStatus = $this->repository->countOverviewByOrganizationId($orgId, null, 'operational');
    self::assertSame(2, $byStatus['total']);
    self::assertSame(2, $byStatus['operational']);
    self::assertSame(0, $byStatus['in_stock']);
  }

  /**
   * @param list<Equipment> $equipments
   *
   * @return list<string>
   */
  private function listIds(array $equipments): array
  {
    return array_map(static fn (Equipment $equipment): string => (string) $equipment->id(), $equipments);
  }

  private function persistRecord(
    string $id,
    string $type = 'fire_extinguisher',
    string $status = 'operational',
    string $recordStatus = 'published',
    ?string $serialNumber = null,
    ?string $brand = null,
    ?string $model = null,
    ?string $subType = null,
    ?string $facilityId = null,
    ?string $locationLabel = null,
    ?DateTimeImmutable $createdAt = null,
  ): void {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    $timestamp = $createdAt ?? new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $record = new EquipmentRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->recordStatus = $recordStatus;
    $record->type = $type;
    $record->status = $status;
    $record->serialNumber = $serialNumber;
    $record->brand = $brand;
    $record->model = $model;
    $record->subType = $subType;
    $record->facilityId = $facilityId;
    $record->locationLabel = $locationLabel;
    $record->createdAt = $timestamp;
    $record->updatedAt = $timestamp;
    $this->entityManager->persist($record);
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Equipment Repository Coverage';
    $organization->slug = 'equipment-repository-coverage';
    $organization->ownerUserId = '770e8400-e29b-41d4-a716-4466554e9000';
    $organization->createdByUserId = '770e8400-e29b-41d4-a716-4466554e9000';
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
      'DELETE FROM equipment WHERE organization_id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
