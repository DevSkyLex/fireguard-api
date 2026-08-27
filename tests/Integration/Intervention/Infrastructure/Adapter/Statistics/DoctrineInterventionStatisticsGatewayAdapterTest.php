<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Statistics;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Statistics\DoctrineInterventionStatisticsGatewayAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function str_pad;

use const STR_PAD_LEFT;

/**
 * Test DoctrineInterventionStatisticsGatewayAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineInterventionStatisticsGatewayAdapter::class)]
final class DoctrineInterventionStatisticsGatewayAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'dd0e8400-e29b-41d4-a716-446655440001';

  private const string OTHER_ORGANIZATION_ID = 'dd0e8400-e29b-41d4-a716-446655440002';

  private const string SITE_A = 'dd0e8400-e29b-41d4-a716-446655440010';

  private const string SITE_B = 'dd0e8400-e29b-41d4-a716-446655440011';

  private const string RESPONSIBLE_A = 'dd0e8400-e29b-41d4-a716-446655440020';

  private EntityManagerInterface $entityManager;

  private DoctrineInterventionStatisticsGatewayAdapter $adapter;

  private int $sequence = 0;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new DoctrineInterventionStatisticsGatewayAdapter($this->entityManager);

    $this->createOrganization(self::ORGANIZATION_ID, 'stats-gateway-test');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'stats-gateway-other');
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testAggregateGroupsCountsByStatusAndPriorityScopedToTheOrganization(): void
  {
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440100', self::ORGANIZATION_ID, status: 'draft', priority: 'low');
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440101', self::ORGANIZATION_ID, status: 'draft', priority: 'normal');
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440102', self::ORGANIZATION_ID, status: 'published', priority: 'normal');
    // Another organization: must never be counted.
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440103', self::OTHER_ORGANIZATION_ID, status: 'draft', priority: 'low');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $aggregate = $this->adapter->aggregate(self::ORGANIZATION_ID, new DateTimeImmutable('2026-06-15T00:00:00+00:00'));

    self::assertSame(3, $aggregate->total);
    self::assertSame(['draft' => 2, 'published' => 1], $aggregate->countsByStatus);
    self::assertSame(['low' => 1, 'normal' => 2], $aggregate->countsByPriority);
  }

  #[Test]
  public function testAggregateOverdueExcludesTerminalStatusesEvenWithAPastDueDate(): void
  {
    $now = new DateTimeImmutable('2026-06-15T00:00:00+00:00');

    // Non-terminal, past due: counts.
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440110', self::ORGANIZATION_ID, status: 'in_progress', dueAt: $now->modify('-1 day'));
    // Terminal (abandoned), past due: must NOT count.
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440111', self::ORGANIZATION_ID, status: 'abandoned', dueAt: $now->modify('-5 days'));
    // Terminal (published), past due: must NOT count.
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440112', self::ORGANIZATION_ID, status: 'published', dueAt: $now->modify('-5 days'));
    // Non-terminal, future due: must not count.
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440113', self::ORGANIZATION_ID, status: 'planned', dueAt: $now->modify('+1 day'));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $aggregate = $this->adapter->aggregate(self::ORGANIZATION_ID, $now);

    self::assertSame(1, $aggregate->overdue);
  }

  #[Test]
  public function testAggregateDueSoonMatchesTheReminderSweepsWindowAndActiveStatuses(): void
  {
    $now = new DateTimeImmutable('2026-06-15T00:00:00+00:00');

    // Active status, within the 48h window: counts.
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440120', self::ORGANIZATION_ID, status: 'planned', dueAt: $now->modify('+24 hours'));
    // Active status, exactly on the 48h boundary (inclusive): counts.
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440121', self::ORGANIZATION_ID, status: 'in_progress', dueAt: $now->modify('+48 hours'));
    // Active status, just past the 48h boundary: must not count.
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440122', self::ORGANIZATION_ID, status: 'changes_requested', dueAt: $now->modify('+49 hours'));
    // Not an active status (draft is not yet scheduled): must not count even within the window.
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440123', self::ORGANIZATION_ID, status: 'draft', dueAt: $now->modify('+1 hour'));
    // Already past due: must not count toward due-soon (it is overdue instead).
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440124', self::ORGANIZATION_ID, status: 'planned', dueAt: $now->modify('-1 hour'));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $aggregate = $this->adapter->aggregate(self::ORGANIZATION_ID, $now);

    self::assertSame(2, $aggregate->dueSoon);
  }

  #[Test]
  public function testAggregateTopSitesAndResponsiblesAreOrderedDescendingAndBoundedToTen(): void
  {
    $now = new DateTimeImmutable('2026-06-15T00:00:00+00:00');

    // Site A: 3 interventions, Site B: 1. Twelve more distinct sites with a
    // single intervention each to exercise the top-10 truncation.
    for ($i = 0; $i < 3; ++$i) {
      $this->createIntervention('dd0e8400-e29b-41d4-a716-4466554402' . (10 + $i), self::ORGANIZATION_ID, siteId: self::SITE_A, responsibleId: self::RESPONSIBLE_A);
    }
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440213', self::ORGANIZATION_ID, siteId: self::SITE_B);
    for ($i = 0; $i < 12; ++$i) {
      $siteId = 'dd0e8400-e29b-41d4-a716-4466554403' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
      $this->createIntervention('dd0e8400-e29b-41d4-a716-4466554404' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), self::ORGANIZATION_ID, siteId: $siteId);
    }
    $this->entityManager->flush();
    $this->entityManager->clear();

    $aggregate = $this->adapter->aggregate(self::ORGANIZATION_ID, $now);

    self::assertCount(10, $aggregate->topSites, 'bySite must be bounded to the top 10.');
    self::assertSame(self::SITE_A, $aggregate->topSites[0]->id, 'The site with the most interventions must come first.');
    self::assertSame(3, $aggregate->topSites[0]->count);

    self::assertCount(1, $aggregate->topResponsibles);
    self::assertSame(self::RESPONSIBLE_A, $aggregate->topResponsibles[0]->id);
    self::assertSame(3, $aggregate->topResponsibles[0]->count);
  }

  #[Test]
  public function testAggregateAveragePublicationDaysIsNullWithoutAnyPublishedInterventionAndComputedOtherwise(): void
  {
    $now = new DateTimeImmutable('2026-06-15T00:00:00+00:00');

    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440300', self::ORGANIZATION_ID, status: 'draft');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $withoutPublished = $this->adapter->aggregate(self::ORGANIZATION_ID, $now);
    self::assertNull($withoutPublished->averagePublicationDays);

    $createdAt = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    // Published 2 days after creation.
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440301', self::ORGANIZATION_ID, status: 'published', createdAt: $createdAt, updatedAt: $createdAt->modify('+2 days'));
    // Published 4 days after creation. Mean of (2, 4) = 3.0.
    $this->createIntervention('dd0e8400-e29b-41d4-a716-446655440302', self::ORGANIZATION_ID, status: 'published', createdAt: $createdAt, updatedAt: $createdAt->modify('+4 days'));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $withPublished = $this->adapter->aggregate(self::ORGANIZATION_ID, $now);
    self::assertNotNull($withPublished->averagePublicationDays);
    self::assertEqualsWithDelta(3.0, $withPublished->averagePublicationDays, 0.001);
  }

  private function createIntervention(
    string $id,
    string $organizationId,
    string $status = 'draft',
    string $priority = 'normal',
    ?string $siteId = null,
    ?string $responsibleId = null,
    ?DateTimeImmutable $dueAt = null,
    ?DateTimeImmutable $createdAt = null,
    ?DateTimeImmutable $updatedAt = null,
  ): void {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $record = new InterventionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->name = 'Statistics Gateway Test ' . (++$this->sequence);
    $record->number = $this->sequence;
    $record->status = $status;
    $record->priority = $priority;
    $record->siteId = $siteId;
    $record->responsibleId = $responsibleId;
    $record->dueAt = $dueAt;
    $record->createdAt = $createdAt ?? new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = $updatedAt ?? $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function createOrganization(string $id, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Statistics Gateway ' . $slug;
    $organization->slug = $slug;
    $organization->ownerUserId = 'dd0e8400-e29b-41d4-a716-446655449000';
    $organization->createdByUserId = 'dd0e8400-e29b-41d4-a716-446655449000';
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
