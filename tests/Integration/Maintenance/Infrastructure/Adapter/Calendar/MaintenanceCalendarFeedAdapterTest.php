<?php

declare(strict_types=1);

namespace Tests\Integration\Maintenance\Infrastructure\Adapter\Calendar;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Infrastructure\Adapter\Calendar\MaintenanceCalendarFeedAdapter;
use Maintenance\Infrastructure\Persistence\Doctrine\Record\MaintenanceScheduleRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function substr;

/**
 * Test MaintenanceCalendarFeedAdapterTest.
 *
 * Exercises the real DQL behind `findBetween()` against a live entity
 * manager.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceCalendarFeedAdapter::class)]
final class MaintenanceCalendarFeedAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655480001';

  private const string OTHER_ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655480002';

  private const string IN_RANGE_ID = '880e8400-e29b-41d4-a716-446655480010';

  private const string OVERDUE_IN_RANGE_ID = '880e8400-e29b-41d4-a716-446655480011';

  private const string OUT_OF_RANGE_ID = '880e8400-e29b-41d4-a716-446655480012';

  private const string NO_DUE_DATE_ID = '880e8400-e29b-41d4-a716-446655480013';

  private const string OTHER_ORGANIZATION_SCHEDULE_ID = '880e8400-e29b-41d4-a716-446655480014';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();
    $this->createOrganization(self::ORGANIZATION_ID);
    $this->createOrganization(self::OTHER_ORGANIZATION_ID);
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function itReturnsScheduleWhoseNextDueAtFallsWithinRangeRegardlessOfDueStatus(): void
  {
    $this->createSchedule(self::IN_RANGE_ID, self::ORGANIZATION_ID, new DateTimeImmutable('2026-08-15T00:00:00+00:00'), 'due_soon');

    // Even an already-`overdue` schedule surfaces if its due date is in the requested window.
    $this->createSchedule(self::OVERDUE_IN_RANGE_ID, self::ORGANIZATION_ID, new DateTimeImmutable('2026-08-05T00:00:00+00:00'), 'overdue');

    $this->createSchedule(self::OUT_OF_RANGE_ID, self::ORGANIZATION_ID, new DateTimeImmutable('2026-09-15T00:00:00+00:00'), 'up_to_date');

    $this->createSchedule(self::NO_DUE_DATE_ID, self::ORGANIZATION_ID, null, 'unscheduled');

    $this->createSchedule(self::OTHER_ORGANIZATION_SCHEDULE_ID, self::OTHER_ORGANIZATION_ID, new DateTimeImmutable('2026-08-15T00:00:00+00:00'), 'due_soon');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $adapter = new MaintenanceCalendarFeedAdapter($this->entityManager);

    $items = $adapter->findBetween(
      self::ORGANIZATION_ID,
      new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-08-31T23:59:59+00:00'),
      500,
    );

    self::assertCount(2, $items);
    self::assertSame(self::OVERDUE_IN_RANGE_ID, $items[0]->id);
    self::assertSame('overdue', $items[0]->status);
    self::assertSame('maintenance', $items[0]->sourceKey);
    self::assertSame('maintenance_schedule', $items[0]->targetType);
    self::assertSame(self::IN_RANGE_ID, $items[1]->id);
    self::assertSame('due_soon', $items[1]->status);
  }

  private function createSchedule(string $id, string $organizationId, ?DateTimeImmutable $nextDueAt, string $dueStatus): void
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    // Each schedule gets its own equipmentId: `(organization_id, equipment_id)`
    // is unique, and several schedules share `$organizationId` in these tests.
    $schedule = new MaintenanceScheduleRecord();
    $schedule->id = $id;
    $schedule->organization = $organization;
    $schedule->equipmentId = '880e8400-e29b-41d4-a716-' . substr($id, -12);
    $schedule->equipmentType = 'fire_extinguisher';
    $schedule->nextDueAt = $nextDueAt;
    $schedule->dueStatus = $dueStatus;
    $schedule->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $schedule->updatedAt = $schedule->createdAt;
    $this->entityManager->persist($schedule);
  }

  private function createOrganization(string $id): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Maintenance Calendar Feed Test ' . $id;
    $organization->slug = 'maintenance-calendar-feed-test-' . $id;
    $organization->ownerUserId = '880e8400-e29b-41d4-a716-446655489001';
    $organization->createdByUserId = '880e8400-e29b-41d4-a716-446655489001';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
    $this->entityManager->flush();
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM maintenance_schedules WHERE organization_id IN (:organizationId, :otherOrganizationId)',
      ['organizationId' => self::ORGANIZATION_ID, 'otherOrganizationId' => self::OTHER_ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationId, :otherOrganizationId)',
      ['organizationId' => self::ORGANIZATION_ID, 'otherOrganizationId' => self::OTHER_ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
