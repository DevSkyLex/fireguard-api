<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, MaintenanceLogId};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Infrastructure\Persistence\Doctrine\Repository\MaintenanceLogRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function is_numeric;

/**
 * Test MaintenanceLogRepositoryTest.
 *
 * Exercises the raw-DBAL idempotence guard in
 * `appendInterventionServiceEntry()` against a real database — hard to
 * trust from a mock, mirrors
 * `Tests\Integration\Intervention\Infrastructure\Adapter\Recurrence\DoctrineInterventionRecurrenceAdapterTest::testReserveRunIsIdempotentPerRecurrenceAndOccurrenceDate`.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceLogRepository::class)]
final class MaintenanceLogRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655450001';

  private const string EQUIPMENT_ID = '660e8400-e29b-41d4-a716-446655450002';

  private EntityManagerInterface $entityManager;

  private MaintenanceLogRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new MaintenanceLogRepository($this->entityManager);

    $this->createOrganization();
    $this->createEquipment();
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testAppendInterventionServiceEntryIsIdempotentPerDedupKey(): void
  {
    $occurredAt = new DateTimeImmutable('2026-07-15T10:00:00+00:00');

    $firstEntry = EquipmentMaintenanceLog::recordInterventionService(
      id: MaintenanceLogId::fromString('660e8400-e29b-41d4-a716-446655450010'),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      occurredAt: $occurredAt,
      interventionId: '660e8400-e29b-41d4-a716-446655450020',
      interventionNumber: 1,
      workItemAction: 'status_change',
      actorId: null,
    );

    // First insert succeeds.
    $this->repository->appendInterventionServiceEntry($firstEntry, 'dedup-key-one');

    self::assertSame(1, $this->countLogsForEquipment());

    // Same dedup key, a different log id (simulates an at-least-once
    // redelivery re-generating a fresh uuid): must be a routine no-op, not
    // an exception, and must not close the EntityManager.
    $duplicateEntry = EquipmentMaintenanceLog::recordInterventionService(
      id: MaintenanceLogId::fromString('660e8400-e29b-41d4-a716-446655450011'),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      occurredAt: $occurredAt,
      interventionId: '660e8400-e29b-41d4-a716-446655450020',
      interventionNumber: 1,
      workItemAction: 'status_change',
      actorId: null,
    );
    $this->repository->appendInterventionServiceEntry($duplicateEntry, 'dedup-key-one');

    self::assertSame(1, $this->countLogsForEquipment());
    self::assertTrue($this->entityManager->isOpen());

    // A different dedup key still succeeds after the duplicate above.
    $secondEntry = EquipmentMaintenanceLog::recordInterventionService(
      id: MaintenanceLogId::fromString('660e8400-e29b-41d4-a716-446655450012'),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      occurredAt: $occurredAt,
      interventionId: '660e8400-e29b-41d4-a716-446655450020',
      interventionNumber: 1,
      workItemAction: 'update',
      actorId: null,
    );
    $this->repository->appendInterventionServiceEntry($secondEntry, 'dedup-key-two');

    self::assertSame(2, $this->countLogsForEquipment());
  }

  private function countLogsForEquipment(): int
  {
    $count = $this->entityManager->getConnection()->fetchOne(
      'SELECT COUNT(*) FROM equipment_maintenance_logs WHERE equipment_id = :equipmentId',
      ['equipmentId' => self::EQUIPMENT_ID],
    );

    return is_numeric($count) ? (int) $count : 0;
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Maintenance Log Repository Test';
    $organization->slug = 'maintenance-log-repository-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655459000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655459000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createEquipment(): void
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organization = $organization;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = 'operational';
    $equipment->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $equipment->updatedAt = $equipment->createdAt;
    $this->entityManager->persist($equipment);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM equipment_maintenance_logs WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM equipment WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
