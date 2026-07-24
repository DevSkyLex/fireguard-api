<?php

declare(strict_types=1);

namespace Tests\Integration\Maintenance\Infrastructure\Adapter\Compliance;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Infrastructure\Adapter\Compliance\MaintenanceComplianceStatisticsAdapter;
use Maintenance\Infrastructure\Persistence\Doctrine\Record\MaintenanceScheduleRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function sprintf;

/**
 * Test MaintenanceComplianceStatisticsAdapterTest.
 *
 * Exercises the real raw SQL behind `dueStatusCountsByFacility()` and
 * `lastInspectionClosedAtByFacility()` against a live database — the
 * facility grouping, the `COALESCE(facility_id, 'unassigned')` bucket, and
 * the per-organization scoping are exactly what a mocked connection would
 * hide.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceComplianceStatisticsAdapter::class)]
final class MaintenanceComplianceStatisticsAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655470001';

  private const string OTHER_ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655470002';

  private const string FACILITY_A = '770e8400-e29b-41d4-a716-446655470100';

  private const string FACILITY_B = '770e8400-e29b-41d4-a716-446655470101';

  private EntityManagerInterface $entityManager;

  private MaintenanceComplianceStatisticsAdapter $adapter;

  private int $scheduleSequence = 0;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new MaintenanceComplianceStatisticsAdapter($this->entityManager);

    $this->createOrganization(self::ORGANIZATION_ID);
    $this->createOrganization(self::OTHER_ORGANIZATION_ID);
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testDueStatusCountsByFacilityGroupsAndCountsPerStatus(): void
  {
    $this->createSchedule(self::ORGANIZATION_ID, self::FACILITY_A, 'overdue', null);
    $this->createSchedule(self::ORGANIZATION_ID, self::FACILITY_A, 'overdue', null);
    $this->createSchedule(self::ORGANIZATION_ID, self::FACILITY_A, 'due_soon', null);
    $this->createSchedule(self::ORGANIZATION_ID, self::FACILITY_B, 'up_to_date', null);
    $this->entityManager->flush();

    $counts = $this->adapter->dueStatusCountsByFacility(self::ORGANIZATION_ID);

    self::assertArrayHasKey(self::FACILITY_A, $counts);
    self::assertArrayHasKey(self::FACILITY_B, $counts);
    self::assertSame([
      'up_to_date' => 0,
      'due_soon' => 1,
      'overdue' => 2,
      'unscheduled' => 0,
    ], $counts[self::FACILITY_A]);
    self::assertSame([
      'up_to_date' => 1,
      'due_soon' => 0,
      'overdue' => 0,
      'unscheduled' => 0,
    ], $counts[self::FACILITY_B]);
  }

  #[Test]
  public function testDueStatusCountsBucketsNullFacilityUnderUnassigned(): void
  {
    $this->createSchedule(self::ORGANIZATION_ID, null, 'overdue', null);
    $this->entityManager->flush();

    $counts = $this->adapter->dueStatusCountsByFacility(self::ORGANIZATION_ID);

    self::assertArrayHasKey('unassigned', $counts);
    self::assertSame(1, $counts['unassigned']['overdue']);
  }

  #[Test]
  public function testDueStatusCountsAreScopedToOrganization(): void
  {
    $this->createSchedule(self::OTHER_ORGANIZATION_ID, self::FACILITY_A, 'overdue', null);
    $this->entityManager->flush();

    $counts = $this->adapter->dueStatusCountsByFacility(self::ORGANIZATION_ID);

    self::assertSame([], $counts);
  }

  #[Test]
  public function testLastInspectionClosedAtByFacilityReturnsMaxPerFacility(): void
  {
    $this->createSchedule(self::ORGANIZATION_ID, self::FACILITY_A, 'up_to_date', new DateTimeImmutable('2026-05-01T10:00:00+00:00'));
    $this->createSchedule(self::ORGANIZATION_ID, self::FACILITY_A, 'up_to_date', new DateTimeImmutable('2026-06-15T08:30:00+00:00'));
    $this->createSchedule(self::ORGANIZATION_ID, self::FACILITY_B, 'up_to_date', new DateTimeImmutable('2026-04-20T12:00:00+00:00'));
    $this->entityManager->flush();

    $dates = $this->adapter->lastInspectionClosedAtByFacility(self::ORGANIZATION_ID);

    self::assertArrayHasKey(self::FACILITY_A, $dates);
    self::assertArrayHasKey(self::FACILITY_B, $dates);
    self::assertSame('2026-06-15T08:30:00+00:00', $dates[self::FACILITY_A]);
    self::assertSame('2026-04-20T12:00:00+00:00', $dates[self::FACILITY_B]);
  }

  #[Test]
  public function testLastInspectionClosedAtByFacilityIgnoresNullTimestamps(): void
  {
    $this->createSchedule(self::ORGANIZATION_ID, self::FACILITY_A, 'unscheduled', null);
    $this->entityManager->flush();

    $dates = $this->adapter->lastInspectionClosedAtByFacility(self::ORGANIZATION_ID);

    self::assertArrayNotHasKey(self::FACILITY_A, $dates);
  }

  private function createOrganization(string $id): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Maintenance Compliance Statistics Adapter Test ' . $id;
    $organization->slug = 'maintenance-compliance-statistics-adapter-test-' . $id;
    $organization->ownerUserId = '770e8400-e29b-41d4-a716-446655479000';
    $organization->createdByUserId = '770e8400-e29b-41d4-a716-446655479000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createSchedule(
    string $organizationId,
    ?string $facilityId,
    string $dueStatus,
    ?DateTimeImmutable $lastInspectionClosedAt,
  ): void {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $sequence = ++$this->scheduleSequence;

    $schedule = new MaintenanceScheduleRecord();
    $schedule->id = sprintf('770e8400-e29b-41d4-a716-4466554702%02d', $sequence);
    $schedule->organization = $organization;
    $schedule->equipmentId = sprintf('770e8400-e29b-41d4-a716-4466554703%02d', $sequence);
    $schedule->facilityId = $facilityId;
    $schedule->equipmentType = 'fire_extinguisher';
    $schedule->dueStatus = $dueStatus;
    $schedule->lastInspectionClosedAt = $lastInspectionClosedAt;
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
