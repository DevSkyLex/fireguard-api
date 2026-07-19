<?php

declare(strict_types=1);

namespace Tests\Integration\Maintenance\Infrastructure\Adapter\Equipment;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Infrastructure\Adapter\Equipment\EquipmentMaintenanceDueStatusAdapter;
use Maintenance\Infrastructure\Persistence\Doctrine\Record\MaintenanceScheduleRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test EquipmentMaintenanceDueStatusAdapterTest.
 *
 * Exercises the real DQL query behind `dueStatusesForEquipment()` against a
 * real database — a mocked QueryBuilder never parses the `s.organization = `
 * / `s.equipmentId IN (:equipmentIds)` predicate, and the cross-organization
 * scoping guard (a schedule belonging to another organization must never
 * leak) is exactly the kind of bug a mock would hide.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentMaintenanceDueStatusAdapter::class)]
final class EquipmentMaintenanceDueStatusAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655470001';

  private const string OTHER_ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655470002';

  private const string EQUIPMENT_ID_OVERDUE = '660e8400-e29b-41d4-a716-446655470010';

  private const string EQUIPMENT_ID_UP_TO_DATE = '660e8400-e29b-41d4-a716-446655470011';

  private const string EQUIPMENT_ID_UNSCHEDULED = '660e8400-e29b-41d4-a716-446655470012';

  private const string EQUIPMENT_ID_NO_SCHEDULE = '660e8400-e29b-41d4-a716-446655470013';

  private const string EQUIPMENT_ID_OTHER_ORGANIZATION = '660e8400-e29b-41d4-a716-446655470020';

  private EntityManagerInterface $entityManager;

  private EquipmentMaintenanceDueStatusAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new EquipmentMaintenanceDueStatusAdapter($this->entityManager);

    $this->createOrganization(self::ORGANIZATION_ID);
    $this->createOrganization(self::OTHER_ORGANIZATION_ID);

    $this->createSchedule('660e8400-e29b-41d4-a716-446655470030', self::ORGANIZATION_ID, self::EQUIPMENT_ID_OVERDUE, 'overdue');
    $this->createSchedule('660e8400-e29b-41d4-a716-446655470031', self::ORGANIZATION_ID, self::EQUIPMENT_ID_UP_TO_DATE, 'up_to_date');
    $this->createSchedule('660e8400-e29b-41d4-a716-446655470032', self::ORGANIZATION_ID, self::EQUIPMENT_ID_UNSCHEDULED, 'unscheduled');
    // Belongs to a DIFFERENT organization: must never leak into a lookup scoped to ORGANIZATION_ID.
    $this->createSchedule('660e8400-e29b-41d4-a716-446655470033', self::OTHER_ORGANIZATION_ID, self::EQUIPMENT_ID_OTHER_ORGANIZATION, 'overdue');

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
  public function testDueStatusesForEquipmentResolvesEachRequestedIdInOneBatch(): void
  {
    $result = $this->adapter->dueStatusesForEquipment(self::ORGANIZATION_ID, [
      self::EQUIPMENT_ID_OVERDUE,
      self::EQUIPMENT_ID_UP_TO_DATE,
      self::EQUIPMENT_ID_UNSCHEDULED,
    ]);

    self::assertSame([
      self::EQUIPMENT_ID_OVERDUE => 'overdue',
      self::EQUIPMENT_ID_UP_TO_DATE => 'up_to_date',
      self::EQUIPMENT_ID_UNSCHEDULED => 'unscheduled',
    ], $result);
  }

  #[Test]
  public function testDueStatusesForEquipmentDefaultsMissingScheduleToUnscheduled(): void
  {
    $result = $this->adapter->dueStatusesForEquipment(self::ORGANIZATION_ID, [
      self::EQUIPMENT_ID_NO_SCHEDULE,
    ]);

    self::assertSame([
      self::EQUIPMENT_ID_NO_SCHEDULE => 'unscheduled',
    ], $result);
  }

  #[Test]
  public function testDueStatusesForEquipmentNeverLeaksAnotherOrganizationsSchedule(): void
  {
    $result = $this->adapter->dueStatusesForEquipment(self::ORGANIZATION_ID, [
      self::EQUIPMENT_ID_OTHER_ORGANIZATION,
    ]);

    // The other organization's schedule is `overdue`; scoped to
    // ORGANIZATION_ID it must default to `unscheduled`, not leak `overdue`.
    self::assertSame([
      self::EQUIPMENT_ID_OTHER_ORGANIZATION => 'unscheduled',
    ], $result);
  }

  #[Test]
  public function testDueStatusesForEquipmentReturnsOneEntryPerRequestedIdEvenMixed(): void
  {
    $result = $this->adapter->dueStatusesForEquipment(self::ORGANIZATION_ID, [
      self::EQUIPMENT_ID_OVERDUE,
      self::EQUIPMENT_ID_NO_SCHEDULE,
    ]);

    self::assertCount(2, $result);
    self::assertSame('overdue', $result[self::EQUIPMENT_ID_OVERDUE]);
    self::assertSame('unscheduled', $result[self::EQUIPMENT_ID_NO_SCHEDULE]);
  }

  private function createOrganization(string $id): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Maintenance Due Status Adapter Test ' . $id;
    $organization->slug = 'maintenance-due-status-adapter-test-' . $id;
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655479000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655479000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createSchedule(string $id, string $organizationId, string $equipmentId, string $dueStatus): void
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $schedule = new MaintenanceScheduleRecord();
    $schedule->id = $id;
    $schedule->organization = $organization;
    $schedule->equipmentId = $equipmentId;
    $schedule->equipmentType = 'fire_extinguisher';
    $schedule->dueStatus = $dueStatus;
    $schedule->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $schedule->updatedAt = $schedule->createdAt;
    $this->entityManager->persist($schedule);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM maintenance_schedules WHERE organization_id IN (:organizationIds)',
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
