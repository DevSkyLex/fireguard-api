<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Publication;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException, PublicationNotFoundException};
use Intervention\Infrastructure\Adapter\Publication\DoctrinePublicationAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionChangeRecord, InterventionRecord, PublicationRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function sprintf;

/**
 * Test DoctrinePublicationAdapterTest.
 *
 * Exercises the publication port against the real main database: the
 * pending/processing/completed/failed lifecycle, its idempotent replay
 * guarantees, and the transactional publish that applies proposed changes
 * before flipping the intervention to published.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrinePublicationAdapter::class)]
final class DoctrinePublicationAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '880e8400-e29b-41d4-a716-4466554490a0';

  private const string OWNER_USER_ID = '880e8400-e29b-41d4-a716-4466554490a1';

  private const string RESPONSIBLE_MEMBER_ID = '880e8400-e29b-41d4-a716-4466554490a2';

  private const string FACILITY_ID = '880e8400-e29b-41d4-a716-4466554490a3';

  private const string INTERVENTION_ID = '880e8400-e29b-41d4-a716-4466554490b0';

  private const string PUBLICATION_ID = '880e8400-e29b-41d4-a716-4466554490c0';

  private const string CHANGE_ID = '880e8400-e29b-41d4-a716-4466554490d0';

  private const string ORPHAN_PUBLICATION_ID = '880e8400-e29b-41d4-a716-4466554490e0';

  private const string MISSING_ID = '880e8400-e29b-41d4-a716-4466554490ff';

  private EntityManagerInterface $entityManager;

  private DoctrinePublicationAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    /** @var DoctrinePublicationAdapter $adapter */
    $adapter = static::getContainer()->get(DoctrinePublicationAdapter::class);
    $this->adapter = $adapter;

    $this->seedOrganizationAndIntervention();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    if ($this->entityManager->isOpen()) {
      $this->entityManager->close();
    }
  }

  #[Test]
  public function testInterventionContextExposesTheOrganizationStatusAndRevision(): void
  {
    $context = $this->adapter->interventionContext(self::INTERVENTION_ID);

    self::assertNotNull($context);
    self::assertSame(self::INTERVENTION_ID, $context->interventionId);
    self::assertSame(self::ORGANIZATION_ID, $context->organizationId);
    self::assertSame('submitted', $context->status);
    self::assertSame(1, $context->revision);
  }

  #[Test]
  public function testInterventionContextReturnsNullForAnUnknownIntervention(): void
  {
    self::assertNull($this->adapter->interventionContext(self::MISSING_ID));
  }

  #[Test]
  public function testCreateOrGetPendingCreatesOncePerInterventionRevisionThenReturnsTheSameRow(): void
  {
    $created = $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    self::assertSame(self::PUBLICATION_ID, $created->id);
    self::assertSame(self::INTERVENTION_ID, $created->interventionId);
    self::assertSame(1, $created->interventionRevision);
    self::assertSame('pending', $created->status);
    self::assertNull($created->error);
    self::assertNull($created->completedAt);

    // Same intervention revision: the unique constraint is honored by
    // returning the existing row instead of inserting a second one.
    $again = $this->adapter->createOrGetPending(self::MISSING_ID, self::INTERVENTION_ID, 1);
    self::assertSame(self::PUBLICATION_ID, $again->id);
    self::assertSame(1, $this->entityManager->getRepository(PublicationRecord::class)->count([
      'intervention' => $this->entityManager->getReference(InterventionRecord::class, self::INTERVENTION_ID),
    ]));
  }

  #[Test]
  public function testCreateOrGetPendingRejectsAnUnknownIntervention(): void
  {
    $this->expectException(InterventionNotFoundException::class);

    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::MISSING_ID, 1);
  }

  #[Test]
  public function testFindReturnsTheViewForAKnownPublicationAndNullOtherwise(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    $view = $this->adapter->find(self::PUBLICATION_ID);
    self::assertNotNull($view);
    self::assertSame(self::PUBLICATION_ID, $view->id);
    self::assertSame('pending', $view->status);

    self::assertNull($this->adapter->find(self::MISSING_ID));
  }

  #[Test]
  public function testFindByInterventionRevisionMatchesOnlyTheStoredRevision(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    $view = $this->adapter->findByInterventionRevision(self::INTERVENTION_ID, 1);
    self::assertNotNull($view);
    self::assertSame(self::PUBLICATION_ID, $view->id);

    self::assertNull($this->adapter->findByInterventionRevision(self::INTERVENTION_ID, 2));
  }

  #[Test]
  public function testMarkProcessingAdvancesPendingButNeverATerminalPublication(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    $this->adapter->markProcessing(self::PUBLICATION_ID);
    self::assertSame('processing', $this->adapter->find(self::PUBLICATION_ID)?->status);

    // Unknown ids are a no-op rather than an error: the queue may retry a
    // publication whose row was already removed.
    $this->adapter->markProcessing(self::MISSING_ID);

    $this->setPublicationStatus('completed');
    $this->adapter->markProcessing(self::PUBLICATION_ID);
    self::assertSame('completed', $this->adapter->find(self::PUBLICATION_ID)?->status);

    $this->setPublicationStatus('failed');
    $this->adapter->markProcessing(self::PUBLICATION_ID);
    self::assertSame('failed', $this->adapter->find(self::PUBLICATION_ID)?->status);
  }

  #[Test]
  public function testRetryFailedResetsOnlyFailedPublications(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $this->adapter->markFailed(self::PUBLICATION_ID, 'boom');

    $retried = $this->adapter->retryFailed(self::PUBLICATION_ID);
    self::assertSame('pending', $retried->status);
    self::assertNull($retried->error);
    self::assertNull($retried->completedAt);

    // Already pending: retrying is a read, not a second transition.
    $unchanged = $this->adapter->retryFailed(self::PUBLICATION_ID);
    self::assertSame('pending', $unchanged->status);
  }

  #[Test]
  public function testRetryFailedRejectsAnUnknownPublication(): void
  {
    $this->expectException(PublicationNotFoundException::class);

    $this->adapter->retryFailed(self::MISSING_ID);
  }

  #[Test]
  public function testMarkFailedIsIdempotentAndReportsWhetherItTransitioned(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    self::assertTrue($this->adapter->markFailed(self::PUBLICATION_ID, 'first failure'));
    $failed = $this->adapter->find(self::PUBLICATION_ID);
    self::assertSame('failed', $failed?->status);
    self::assertSame('first failure', $failed->error);
    self::assertNotNull($failed->completedAt);

    // Terminal already: the first error message is preserved.
    self::assertFalse($this->adapter->markFailed(self::PUBLICATION_ID, 'second failure'));
    self::assertSame('first failure', $this->adapter->find(self::PUBLICATION_ID)?->error);

    self::assertFalse($this->adapter->markFailed(self::MISSING_ID, 'nothing to fail'));
  }

  #[Test]
  public function testMarkFailedFallsBackToRawSqlWhenTheEntityManagerIsClosed(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $connection = $this->entityManager->getConnection();

    // A failing publish closes the entity manager, which is exactly when the
    // worker still has to record the failure — hence the DBAL fallback.
    $this->entityManager->close();

    self::assertTrue($this->adapter->markFailed(self::PUBLICATION_ID, 'closed-manager failure'));
    self::assertSame('failed', $connection->fetchOne(
      'SELECT status FROM intervention_publications WHERE id = ?',
      [self::PUBLICATION_ID],
    ));
    self::assertSame('closed-manager failure', $connection->fetchOne(
      'SELECT error FROM intervention_publications WHERE id = ?',
      [self::PUBLICATION_ID],
    ));

    // Terminal already: the guarded UPDATE affects no row.
    self::assertFalse($this->adapter->markFailed(self::PUBLICATION_ID, 'again'));
  }

  #[Test]
  public function testPublishAppliesProposedChangesAndTransitionsTheIntervention(): void
  {
    $this->seedProposedFacilityRenameChange();
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    // The transition table requires `pending` to pass through `processing`
    // before reaching `completed` — mirroring the real dispatch path, where
    // ExecutePublicationHandler always calls markProcessing() before publish().
    $this->adapter->markProcessing(self::PUBLICATION_ID);

    self::assertTrue($this->adapter->publish(self::PUBLICATION_ID));

    $this->entityManager->clear();

    $intervention = $this->entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $intervention);
    self::assertSame('published', $intervention->status);
    self::assertSame(2, $intervention->revision);

    $change = $this->entityManager->find(InterventionChangeRecord::class, self::CHANGE_ID);
    self::assertInstanceOf(InterventionChangeRecord::class, $change);
    self::assertSame('applied', $change->status);
    self::assertSame(2, $change->revision);

    $facility = $this->entityManager->find(FacilityRecord::class, self::FACILITY_ID);
    self::assertInstanceOf(FacilityRecord::class, $facility);
    self::assertSame('Renamed by publication', $facility->name);

    $publication = $this->adapter->find(self::PUBLICATION_ID);
    self::assertSame('completed', $publication?->status);
    self::assertNotNull($publication->completedAt);
  }

  #[Test]
  public function testPublishIsIdempotentOnAnAlreadyCompletedPublication(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $this->setPublicationStatus('completed');

    // At-least-once delivery: a replay must not re-transition or re-notify.
    self::assertFalse($this->adapter->publish(self::PUBLICATION_ID));

    $this->entityManager->clear();
    $intervention = $this->entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $intervention);
    self::assertSame('submitted', $intervention->status);
    self::assertSame(1, $intervention->revision);
  }

  #[Test]
  public function testPublishRejectsAnUnknownPublication(): void
  {
    $this->expectException(PublicationNotFoundException::class);

    $this->adapter->publish(self::MISSING_ID);
  }

  #[Test]
  public function testPublishConflictsWhenTheInterventionMovedOnAfterTheJobWasQueued(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    $intervention = $this->entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $intervention);
    $intervention->status = 'draft';
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->expectException(InterventionConflictException::class);

    $this->adapter->publish(self::PUBLICATION_ID);
  }

  #[Test]
  public function testPublishConflictsWhenTheInterventionRevisionMovedOn(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    $intervention = $this->entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $intervention);
    $intervention->revision = 7;
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->expectException(InterventionConflictException::class);

    $this->adapter->publish(self::PUBLICATION_ID);
  }

  #[Test]
  public function testFindRejectsAPublicationWhoseInterventionIsGone(): void
  {
    $this->relaxPublicationInterventionConstraint();
    $this->insertOrphanPublication();

    $this->expectException(PublicationNotFoundException::class);

    $this->adapter->find(self::ORPHAN_PUBLICATION_ID);
  }

  #[Test]
  public function testPublishRejectsAPublicationWhoseInterventionIsGone(): void
  {
    $this->relaxPublicationInterventionConstraint();
    $this->insertOrphanPublication();

    $this->expectException(PublicationNotFoundException::class);

    $this->adapter->publish(self::ORPHAN_PUBLICATION_ID);
  }

  #[Test]
  public function testPublishRejectsAnInterventionWithoutAnOrganization(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $this->entityManager->clear();

    // The mapping forbids this, so the column constraint has to be relaxed to
    // reach the guard. The DDL is transactional in PostgreSQL and is rolled
    // back with the rest of the test.
    $this->entityManager->getConnection()->executeStatement(
      'ALTER TABLE interventions ALTER COLUMN organization_id DROP NOT NULL',
    );
    $this->entityManager->getConnection()->executeStatement(
      'UPDATE interventions SET organization_id = NULL WHERE id = ?',
      [self::INTERVENTION_ID],
    );

    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Intervention organization is unavailable.');

    $this->adapter->publish(self::PUBLICATION_ID);
  }

  /**
   * Drops the guarantees the mapping relies on — the NOT NULL and the foreign
   * key on `intervention_publications.intervention_id` — so the adapter's
   * defensive branches become reachable. PostgreSQL DDL is transactional, so
   * DAMA rolls this back with the test.
   */
  private function relaxPublicationInterventionConstraint(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement('ALTER TABLE intervention_publications ALTER COLUMN intervention_id DROP NOT NULL');

    /** @var list<string> $constraints */
    $constraints = $connection->fetchFirstColumn(
      "SELECT conname FROM pg_constraint WHERE conrelid = 'intervention_publications'::regclass AND contype = 'f'",
    );
    foreach ($constraints as $constraint) {
      $connection->executeStatement(sprintf('ALTER TABLE intervention_publications DROP CONSTRAINT %s', $constraint));
    }
  }

  private function insertOrphanPublication(): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO intervention_publications (id, intervention_id, intervention_revision, status, created_at) VALUES (?, NULL, 1, ?, ?)',
      [self::ORPHAN_PUBLICATION_ID, 'pending', '2026-05-04T10:00:00'],
    );
    $this->entityManager->clear();
  }

  private function seedOrganizationAndIntervention(): void
  {
    $createdAt = new DateTimeImmutable('2026-05-04T08:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Publication Adapter Test';
    $organization->slug = 'publication-adapter-test';
    $organization->ownerUserId = self::OWNER_USER_ID;
    $organization->createdByUserId = self::OWNER_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $createdAt;
    $organization->updatedAt = $createdAt;
    $this->entityManager->persist($organization);

    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $facility->type = 'site';
    $facility->name = 'Publication Adapter Site';
    $facility->status = 'active';
    $facility->recordStatus = 'published';
    $facility->createdAt = $createdAt;
    $facility->updatedAt = $createdAt;
    $this->entityManager->persist($facility);

    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION_ID;
    $intervention->organization = $organization;
    $intervention->type = 'site_setup';
    $intervention->name = 'Publication Adapter Intervention';
    $intervention->number = 9001;
    $intervention->status = 'submitted';
    $intervention->priority = 'normal';
    $intervention->revision = 1;
    $intervention->responsibleId = self::RESPONSIBLE_MEMBER_ID;
    $intervention->participants = [self::RESPONSIBLE_MEMBER_ID];
    $intervention->createdAt = $createdAt;
    $intervention->updatedAt = $createdAt;
    $this->entityManager->persist($intervention);

    $this->entityManager->flush();
  }

  private function seedProposedFacilityRenameChange(): void
  {
    /** @var InterventionRecord $intervention */
    $intervention = $this->entityManager->getReference(InterventionRecord::class, self::INTERVENTION_ID);

    $change = new InterventionChangeRecord();
    $change->id = self::CHANGE_ID;
    $change->intervention = $intervention;
    $change->resource = sprintf('/api/facilities/%s', self::FACILITY_ID);
    $change->patch = ['name' => 'Renamed by publication'];
    $change->status = 'proposed';
    $change->revision = 1;
    $change->createdAt = new DateTimeImmutable('2026-05-04T09:00:00+00:00');
    $change->updatedAt = $change->createdAt;
    $this->entityManager->persist($change);
    $this->entityManager->flush();
  }

  private function setPublicationStatus(string $status): void
  {
    $publication = $this->entityManager->find(PublicationRecord::class, self::PUBLICATION_ID);
    self::assertInstanceOf(PublicationRecord::class, $publication);
    $publication->status = $status;
    $this->entityManager->flush();
  }
}
