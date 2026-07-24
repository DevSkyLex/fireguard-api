<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Calendar;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Calendar\InterventionCalendarFeedAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test InterventionCalendarFeedAdapterTest.
 *
 * Exercises the real DQL behind `findBetween()` against a live entity
 * manager — a mocked QueryBuilder would never actually parse the
 * `OR`/`COALESCE` clauses this adapter relies on to resolve an
 * intervention's calendar occurrence instant.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionCalendarFeedAdapter::class)]
final class InterventionCalendarFeedAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655470001';

  private const string OTHER_ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655470002';

  private const string IN_RANGE_BY_PLANNED_START_ID = '770e8400-e29b-41d4-a716-446655470010';

  private const string IN_RANGE_BY_DUE_AT_ONLY_ID = '770e8400-e29b-41d4-a716-446655470011';

  private const string OUT_OF_RANGE_ID = '770e8400-e29b-41d4-a716-446655470012';

  private const string NO_DATES_ID = '770e8400-e29b-41d4-a716-446655470013';

  private const string OTHER_ORGANIZATION_INTERVENTION_ID = '770e8400-e29b-41d4-a716-446655470014';

  private EntityManagerInterface $entityManager;

  /**
   * @var array<string, int>
   */
  private array $nextNumberByOrganization = [];

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
  public function itReturnsOnlyInterventionsOccurringWithinRangeForTheRequestedOrganization(): void
  {
    // Occurs via plannedStartAt within range; dueAt is later and differs, so it surfaces as endsAt.
    $this->createIntervention(
      self::IN_RANGE_BY_PLANNED_START_ID,
      self::ORGANIZATION_ID,
      'Planned within range',
      plannedStartAt: new DateTimeImmutable('2026-08-10T09:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-08-10T11:00:00+00:00'),
    );

    // No plannedStartAt at all; occurs purely by dueAt falling in range.
    $this->createIntervention(
      self::IN_RANGE_BY_DUE_AT_ONLY_ID,
      self::ORGANIZATION_ID,
      'Due within range',
      plannedStartAt: null,
      dueAt: new DateTimeImmutable('2026-08-20T15:00:00+00:00'),
    );

    // Both dates outside the requested range.
    $this->createIntervention(
      self::OUT_OF_RANGE_ID,
      self::ORGANIZATION_ID,
      'Out of range',
      plannedStartAt: new DateTimeImmutable('2026-09-15T09:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-09-15T11:00:00+00:00'),
    );

    // Neither date set: must never appear.
    $this->createIntervention(
      self::NO_DATES_ID,
      self::ORGANIZATION_ID,
      'No dates',
      plannedStartAt: null,
      dueAt: null,
    );

    // In range, but a different organization: must never appear.
    $this->createIntervention(
      self::OTHER_ORGANIZATION_INTERVENTION_ID,
      self::OTHER_ORGANIZATION_ID,
      'Other organization',
      plannedStartAt: new DateTimeImmutable('2026-08-12T09:00:00+00:00'),
      dueAt: null,
    );

    $this->entityManager->flush();
    $this->entityManager->clear();

    $adapter = new InterventionCalendarFeedAdapter($this->entityManager);

    $items = $adapter->findBetween(
      self::ORGANIZATION_ID,
      new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-08-31T23:59:59+00:00'),
      500,
    );

    self::assertCount(2, $items);
    self::assertSame(self::IN_RANGE_BY_PLANNED_START_ID, $items[0]->id);
    self::assertEquals(new DateTimeImmutable('2026-08-10T09:00:00+00:00'), $items[0]->startsAt);
    self::assertEquals(new DateTimeImmutable('2026-08-10T11:00:00+00:00'), $items[0]->endsAt);
    self::assertSame('intervention', $items[0]->sourceKey);

    self::assertSame(self::IN_RANGE_BY_DUE_AT_ONLY_ID, $items[1]->id);
    self::assertEquals(new DateTimeImmutable('2026-08-20T15:00:00+00:00'), $items[1]->startsAt);
    self::assertNull($items[1]->endsAt);
  }

  private function createIntervention(
    string $id,
    string $organizationId,
    string $name,
    ?DateTimeImmutable $plannedStartAt,
    ?DateTimeImmutable $dueAt,
  ): void {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    // `(organization_id, number)` is unique: several interventions share
    // `$organizationId` in these tests, so `number` must be allocated
    // per-organization rather than hardcoded.
    $number = $this->nextNumberByOrganization[$organizationId] ?? 1;
    $this->nextNumberByOrganization[$organizationId] = $number + 1;

    $intervention = new InterventionRecord();
    $intervention->id = $id;
    $intervention->organization = $organization;
    $intervention->type = 'site_setup';
    $intervention->name = $name;
    $intervention->number = $number;
    $intervention->status = 'planned';
    $intervention->plannedStartAt = $plannedStartAt;
    $intervention->dueAt = $dueAt;
    $intervention->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $intervention->updatedAt = $intervention->createdAt;
    $this->entityManager->persist($intervention);
  }

  private function createOrganization(string $id): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Calendar Feed Test ' . $id;
    $organization->slug = 'calendar-feed-test-' . $id;
    $organization->ownerUserId = '770e8400-e29b-41d4-a716-446655479000';
    $organization->createdByUserId = '770e8400-e29b-41d4-a716-446655479000';
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
      'DELETE FROM interventions WHERE organization_id IN (:organizationId, :otherOrganizationId)',
      ['organizationId' => self::ORGANIZATION_ID, 'otherOrganizationId' => self::OTHER_ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationId, :otherOrganizationId)',
      ['organizationId' => self::ORGANIZATION_ID, 'otherOrganizationId' => self::OTHER_ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
