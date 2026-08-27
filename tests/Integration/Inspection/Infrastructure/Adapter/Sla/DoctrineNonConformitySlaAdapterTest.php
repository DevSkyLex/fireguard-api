<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Adapter\Sla;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Application\Contract\Sla\NonConformitySlaCandidate;
use Inspection\Application\Port\Outbound\NonConformityRepositoryPort;
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{NonConformityId, NonConformityInspectionId, NonConformitySeverity, NonConformityStatus};
use Inspection\Infrastructure\Adapter\Sla\DoctrineNonConformitySlaAdapter;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function count;

/**
 * Test DoctrineNonConformitySlaAdapterTest.
 *
 * Also covers the re-arm rule in `NonConformityRepository::save()`: reopening
 * a resolved non-conformity clears `sla_breach_notified_at`, putting it back
 * in the sweep's candidate set.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineNonConformitySlaAdapter::class)]
final class DoctrineNonConformitySlaAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'c31e8400-e29b-41d4-a716-446655c31001';

  private const string INSPECTION_ID = 'c31e8400-e29b-41d4-a716-446655c31002';

  private const string NC_OPEN = 'c31e8400-e29b-41d4-a716-446655c31c01';

  private const string NC_IN_PROGRESS = 'c31e8400-e29b-41d4-a716-446655c31c02';

  private const string NC_RESOLVED = 'c31e8400-e29b-41d4-a716-446655c31c03';

  private const string NC_ALREADY_NOTIFIED = 'c31e8400-e29b-41d4-a716-446655c31c04';

  private const string OWNER_USER_ID = 'c31e8400-e29b-41d4-a716-446655c31900';

  private EntityManagerInterface $entityManager;

  private DoctrineNonConformitySlaAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new DoctrineNonConformitySlaAdapter($this->entityManager);

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'SLA Sweep Org';
    $organization->slug = 'sla-sweep-org';
    $organization->ownerUserId = self::OWNER_USER_ID;
    $organization->createdByUserId = self::OWNER_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;
    $inspection->organization = $organization;
    $inspection->facilityId = null;
    $inspection->equipmentId = 'c31e8400-e29b-41d4-a716-446655c31800';
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Jane Doe';
    $inspection->result = 'fail';
    $inspection->status = 'submitted';
    $inspection->performedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $inspection->createdAt = $inspection->performedAt;
    $inspection->updatedAt = $inspection->performedAt;
    $this->entityManager->persist($inspection);

    $this->entityManager->persist($this->nonConformity(self::NC_OPEN, $inspection, 'critical', 'open'));
    $this->entityManager->persist($this->nonConformity(self::NC_IN_PROGRESS, $inspection, 'high', 'in_progress'));
    $this->entityManager->persist($this->nonConformity(self::NC_RESOLVED, $inspection, 'critical', 'done'));
    $alreadyNotified = $this->nonConformity(self::NC_ALREADY_NOTIFIED, $inspection, 'critical', 'open');
    $alreadyNotified->slaBreachNotifiedAt = new DateTimeImmutable('2026-01-05T00:00:00+00:00');
    $this->entityManager->persist($alreadyNotified);

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
  public function testPageOpenUnnotifiedSelectsOnlyUnresolvedUnstampedCandidates(): void
  {
    // The query is deliberately organization-agnostic (the sweep crosses every
    // organization), and the shared fixtures seed their own non-conformities —
    // assertions are therefore membership-based, scoped to this test's rows.
    $candidates = $this->allCandidatesById();

    self::assertArrayHasKey(self::NC_OPEN, $candidates);
    self::assertArrayHasKey(self::NC_IN_PROGRESS, $candidates);
    self::assertArrayNotHasKey(self::NC_RESOLVED, $candidates);
    self::assertArrayNotHasKey(self::NC_ALREADY_NOTIFIED, $candidates);

    $open = $candidates[self::NC_OPEN];
    self::assertSame(self::ORGANIZATION_ID, $open->organizationId);
    self::assertSame(self::INSPECTION_ID, $open->inspectionId);
    self::assertSame('critical', $open->severity);
  }

  #[Test]
  public function testMarkSlaBreachNotifiedRemovesTheCandidateFromTheNextPage(): void
  {
    $this->adapter->markSlaBreachNotified(self::NC_OPEN, new DateTimeImmutable('2026-01-10T09:00:00+00:00'));
    $this->entityManager->clear();

    $candidates = $this->allCandidatesById();
    self::assertArrayNotHasKey(self::NC_OPEN, $candidates);
    self::assertArrayHasKey(self::NC_IN_PROGRESS, $candidates);
  }

  #[Test]
  public function testReopeningAResolvedNonConformityClearsTheStampAndReArmsTheSweep(): void
  {
    // NC_RESOLVED is `done`; stamp it as if it had been escalated before its
    // resolution. Saving a reopened aggregate over the resolved row must
    // clear the stamp so a still-breached reopened non-conformity is
    // escalated again. The aggregate is reconstituted as `open` directly
    // because `updateStatus()` deliberately rejects reopening today — the
    // persistence-level guard is written for the day a reopen path exists.
    $stamped = $this->entityManager->find(NonConformityRecord::class, self::NC_RESOLVED);
    self::assertNotNull($stamped);
    $stamped->slaBreachNotifiedAt = new DateTimeImmutable('2026-01-05T00:00:00+00:00');
    $this->entityManager->flush();
    $this->entityManager->clear();

    /** @var NonConformityRepositoryPort $repository */
    $repository = static::getContainer()->get(NonConformityRepositoryPort::class);

    $reopened = NonConformity::reconstitute(
      id: NonConformityId::fromString(self::NC_RESOLVED),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Defect ' . self::NC_RESOLVED,
      severity: NonConformitySeverity::CRITICAL,
      status: NonConformityStatus::OPEN,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-11T00:00:00+00:00'),
    );
    $repository->save($reopened);
    $this->entityManager->clear();

    self::assertArrayHasKey(self::NC_RESOLVED, $this->allCandidatesById());
  }

  /**
   * Pages through the full candidate set and indexes it by non-conformity id.
   *
   * @return array<string, NonConformitySlaCandidate>
   */
  private function allCandidatesById(): array
  {
    $candidates = [];
    $offset = 0;

    do {
      $page = $this->adapter->pageOpenUnnotified(200, $offset);
      foreach ($page->items as $candidate) {
        $candidates[$candidate->id] = $candidate;
      }
      $offset += 200;
    } while (200 === count($page->items));

    return $candidates;
  }

  private function nonConformity(string $id, InspectionRecord $inspection, string $severity, string $status): NonConformityRecord
  {
    $record = new NonConformityRecord();
    $record->id = $id;
    $record->inspection = $inspection;
    $record->description = 'Defect ' . $id;
    $record->severity = $severity;
    $record->status = $status;
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = $record->createdAt;

    return $record;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM non_conformities WHERE inspection_id = :inspectionId',
      ['inspectionId' => self::INSPECTION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM inspections WHERE id IN (:inspectionIds)',
      ['inspectionIds' => [self::INSPECTION_ID]],
      ['inspectionIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
