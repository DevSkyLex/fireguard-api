<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Organization;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Organization\InterventionStatisticsAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test InterventionStatisticsAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionStatisticsAdapter::class)]
final class InterventionStatisticsAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'cc0e8400-e29b-41d4-a716-446655440001';

  private const string OTHER_ORGANIZATION_ID = 'cc0e8400-e29b-41d4-a716-446655440002';

  private EntityManagerInterface $entityManager;

  private InterventionStatisticsAdapter $adapter;

  private int $sequence = 0;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new InterventionStatisticsAdapter($this->entityManager);

    $this->createOrganization(self::ORGANIZATION_ID, 'statistics-adapter-test');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'statistics-adapter-other');
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testFindRecentInterventionsIsScopedOrderedByUpdatedAtDescendingAndLimited(): void
  {
    $this->createIntervention('cc0e8400-e29b-41d4-a716-446655440010', self::ORGANIZATION_ID, 'Oldest', updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    $this->createIntervention('cc0e8400-e29b-41d4-a716-446655440011', self::ORGANIZATION_ID, 'Middle', updatedAt: new DateTimeImmutable('2026-03-01T00:00:00+00:00'));
    $this->createIntervention('cc0e8400-e29b-41d4-a716-446655440012', self::ORGANIZATION_ID, 'Newest', updatedAt: new DateTimeImmutable('2026-06-01T00:00:00+00:00'));
    // Belongs to another organization: must never appear.
    $this->createIntervention('cc0e8400-e29b-41d4-a716-446655440013', self::OTHER_ORGANIZATION_ID, 'Foreign', updatedAt: new DateTimeImmutable('2026-07-01T00:00:00+00:00'));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $recent = $this->adapter->findRecentInterventions(self::ORGANIZATION_ID, 2);

    self::assertCount(2, $recent);
    $names = array_map(static fn ($summary): string => $summary->name, $recent);
    self::assertSame(['Newest', 'Middle'], $names);
  }

  #[Test]
  public function testCountOverviewCountsTotalOpenAndOverdue(): void
  {
    $now = new DateTimeImmutable('2026-06-15T00:00:00+00:00');

    // Open, overdue (due in the past, not closed).
    $this->createIntervention('cc0e8400-e29b-41d4-a716-446655440020', self::ORGANIZATION_ID, 'Overdue open', status: 'in_progress', dueAt: $now->modify('-1 day'));
    // Open, not overdue (due in the future).
    $this->createIntervention('cc0e8400-e29b-41d4-a716-446655440021', self::ORGANIZATION_ID, 'Future open', status: 'draft', dueAt: $now->modify('+1 day'));
    // Open, no due date (never overdue).
    $this->createIntervention('cc0e8400-e29b-41d4-a716-446655440022', self::ORGANIZATION_ID, 'Open no due', status: 'submitted', dueAt: null);
    // Closed (published): counts toward total but not open, and never overdue
    // even though its due date is in the past.
    $this->createIntervention('cc0e8400-e29b-41d4-a716-446655440023', self::ORGANIZATION_ID, 'Closed overdue', status: 'published', dueAt: $now->modify('-5 days'));
    // Another organization: must not be counted at all.
    $this->createIntervention('cc0e8400-e29b-41d4-a716-446655440024', self::OTHER_ORGANIZATION_ID, 'Foreign', status: 'in_progress', dueAt: $now->modify('-1 day'));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $overview = $this->adapter->countOverview(self::ORGANIZATION_ID, $now);

    self::assertSame(4, $overview['total']);
    self::assertSame(3, $overview['open']);
    self::assertSame(1, $overview['overdue']);
  }

  private function createIntervention(
    string $id,
    string $organizationId,
    string $name,
    string $status = 'draft',
    ?DateTimeImmutable $dueAt = null,
    ?DateTimeImmutable $updatedAt = null,
  ): void {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $record = new InterventionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->name = $name;
    $record->number = ++$this->sequence;
    $record->status = $status;
    $record->dueAt = $dueAt;
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = $updatedAt ?? $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function createOrganization(string $id, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Statistics Adapter ' . $slug;
    $organization->slug = $slug;
    $organization->ownerUserId = 'cc0e8400-e29b-41d4-a716-446655449000';
    $organization->createdByUserId = 'cc0e8400-e29b-41d4-a716-446655449000';
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
      'DELETE FROM interventions WHERE organization_id IN (:organizationIds)',
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
