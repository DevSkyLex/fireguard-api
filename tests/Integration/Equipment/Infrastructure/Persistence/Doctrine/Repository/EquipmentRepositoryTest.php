<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentStatus, EquipmentType};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Infrastructure\Persistence\Doctrine\Repository\EquipmentRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test EquipmentRepositoryTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentRepository::class)]
final class EquipmentRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-4466554c0001';

  private const string OTHER_ORGANIZATION_ID = '660e8400-e29b-41d4-a716-4466554c0002';

  private EntityManagerInterface $entityManager;

  private EquipmentRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new EquipmentRepository($this->entityManager);

    $this->createOrganization(self::ORGANIZATION_ID, 'Equipment Repository Test', 'equipment-repository-test');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Equipment Repository Other', 'equipment-repository-other');
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveAndFindByIdRoundTrip(): void
  {
    $id = EquipmentId::fromString('660e8400-e29b-41d4-a716-4466554c0010');
    $this->repository->save($this->buildEquipment(
      id: '660e8400-e29b-41d4-a716-4466554c0010',
      organizationId: self::ORGANIZATION_ID,
      type: EquipmentType::FIRE_EXTINGUISHER,
      status: EquipmentStatus::OPERATIONAL,
      serialNumber: 'SN-ROUNDTRIP-1',
    ));
    $this->entityManager->clear();

    $found = $this->repository->findById($id);

    self::assertInstanceOf(Equipment::class, $found);
    self::assertSame(self::ORGANIZATION_ID, (string) $found->organizationId());
    self::assertSame(EquipmentType::FIRE_EXTINGUISHER, $found->type());
    self::assertSame(EquipmentStatus::OPERATIONAL, $found->status());
    self::assertSame('SN-ROUNDTRIP-1', $found->serialNumber());
  }

  #[Test]
  public function testFindByIdReturnsNullWhenMissing(): void
  {
    self::assertNull(
      $this->repository->findById(EquipmentId::fromString('660e8400-e29b-41d4-a716-4466554c00ff')),
    );
  }

  #[Test]
  public function testFindPublishedByIdIgnoresDraftRecords(): void
  {
    $publishedId = '660e8400-e29b-41d4-a716-4466554c0020';
    $draftId = '660e8400-e29b-41d4-a716-4466554c0021';
    $this->persistEquipmentRecord($publishedId, self::ORGANIZATION_ID, 'published', 'SN-PUB');
    $this->persistEquipmentRecord($draftId, self::ORGANIZATION_ID, 'draft', 'SN-DRAFT');
    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertInstanceOf(
      Equipment::class,
      $this->repository->findPublishedById(EquipmentId::fromString($publishedId)),
    );
    self::assertNull(
      $this->repository->findPublishedById(EquipmentId::fromString($draftId)),
    );
    // findById still returns the draft (no record-status filter).
    self::assertInstanceOf(
      Equipment::class,
      $this->repository->findById(EquipmentId::fromString($draftId)),
    );
  }

  #[Test]
  public function testFindByOrganizationIdIsScopedPaginatedAndExcludesDrafts(): void
  {
    $this->persistEquipmentRecord('660e8400-e29b-41d4-a716-4466554c0030', self::ORGANIZATION_ID, 'published', 'SN-A', new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    $this->persistEquipmentRecord('660e8400-e29b-41d4-a716-4466554c0031', self::ORGANIZATION_ID, 'published', 'SN-B', new DateTimeImmutable('2026-01-02T00:00:00+00:00'));
    $this->persistEquipmentRecord('660e8400-e29b-41d4-a716-4466554c0032', self::ORGANIZATION_ID, 'draft', 'SN-C', new DateTimeImmutable('2026-01-03T00:00:00+00:00'));
    // Another organization's equipment must never leak in.
    $this->persistEquipmentRecord('660e8400-e29b-41d4-a716-4466554c0033', self::OTHER_ORGANIZATION_ID, 'published', 'SN-D', new DateTimeImmutable('2026-01-04T00:00:00+00:00'));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $orgId = EquipmentOrganizationId::fromString(self::ORGANIZATION_ID);

    // Two published records for the organization; draft and other-org excluded.
    self::assertSame(2, $this->repository->countByOrganizationId($orgId));

    $page = $this->repository->findByOrganizationId($orgId, limit: 1, offset: 0);
    self::assertCount(1, $page);
    self::assertSame('660e8400-e29b-41d4-a716-4466554c0030', (string) $page[0]->id());

    $secondPage = $this->repository->findByOrganizationId($orgId, limit: 1, offset: 1);
    self::assertCount(1, $secondPage);
    self::assertSame('660e8400-e29b-41d4-a716-4466554c0031', (string) $secondPage[0]->id());
  }

  #[Test]
  public function testCountOverviewByOrganizationIdBucketsByStatus(): void
  {
    $this->persistEquipmentRecord('660e8400-e29b-41d4-a716-4466554c0040', self::ORGANIZATION_ID, 'published', 'SN-OP-1', null, 'operational');
    $this->persistEquipmentRecord('660e8400-e29b-41d4-a716-4466554c0041', self::ORGANIZATION_ID, 'published', 'SN-OP-2', null, 'operational');
    $this->persistEquipmentRecord('660e8400-e29b-41d4-a716-4466554c0042', self::ORGANIZATION_ID, 'published', 'SN-STOCK', null, 'in_stock');
    $this->persistEquipmentRecord('660e8400-e29b-41d4-a716-4466554c0043', self::ORGANIZATION_ID, 'published', 'SN-DECOM', null, 'decommissioned');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $overview = $this->repository->countOverviewByOrganizationId(
      EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
    );

    self::assertSame(4, $overview['total']);
    self::assertSame(2, $overview['operational']);
    self::assertSame(1, $overview['in_stock']);
    self::assertSame(1, $overview['decommissioned']);
    self::assertSame(0, $overview['under_maintenance']);
  }

  #[Test]
  public function testCountByStatusForOrganizationId(): void
  {
    $this->persistEquipmentRecord('660e8400-e29b-41d4-a716-4466554c0050', self::ORGANIZATION_ID, 'published', 'SN-S1', null, 'operational');
    $this->persistEquipmentRecord('660e8400-e29b-41d4-a716-4466554c0051', self::ORGANIZATION_ID, 'published', 'SN-S2', null, 'operational');
    $this->persistEquipmentRecord('660e8400-e29b-41d4-a716-4466554c0052', self::ORGANIZATION_ID, 'published', 'SN-S3', null, 'under_maintenance');
    // Draft excluded from the status counts.
    $this->persistEquipmentRecord('660e8400-e29b-41d4-a716-4466554c0053', self::ORGANIZATION_ID, 'draft', 'SN-S4', null, 'operational');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $counts = $this->repository->countByStatusForOrganizationId(
      EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
    );

    self::assertSame(2, $counts['operational'] ?? 0);
    self::assertSame(1, $counts['under_maintenance'] ?? 0);
  }

  private function buildEquipment(
    string $id,
    string $organizationId,
    EquipmentType $type,
    EquipmentStatus $status,
    ?string $serialNumber,
  ): Equipment {
    return Equipment::reconstitute(
      id: EquipmentId::fromString($id),
      organizationId: EquipmentOrganizationId::fromString($organizationId),
      type: $type,
      status: $status,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      serialNumber: $serialNumber,
    );
  }

  private function persistEquipmentRecord(
    string $id,
    string $organizationId,
    string $recordStatus,
    ?string $serialNumber,
    ?DateTimeImmutable $createdAt = null,
    string $status = 'operational',
  ): void {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);
    $timestamp = $createdAt ?? new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $equipment = new EquipmentRecord();
    $equipment->id = $id;
    $equipment->organization = $organization;
    $equipment->recordStatus = $recordStatus;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = $status;
    $equipment->serialNumber = $serialNumber;
    $equipment->createdAt = $timestamp;
    $equipment->updatedAt = $timestamp;
    $this->entityManager->persist($equipment);
  }

  private function createOrganization(string $id, string $name, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-4466554c9000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-4466554c9000';
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
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
