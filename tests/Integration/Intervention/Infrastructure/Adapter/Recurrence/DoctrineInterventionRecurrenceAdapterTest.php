<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Recurrence;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Domain\Exception\InterventionNotFoundException;
use Intervention\Infrastructure\Adapter\Recurrence\DoctrineInterventionRecurrenceAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecurrenceRecord, InterventionTemplateRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test DoctrineInterventionRecurrenceAdapter.
 *
 * Exercises the real DQL/DBAL behavior that is hard to trust from a mock:
 * the merge-patch update, name-ordered scoped listing with clamped
 * pagination, the `DATE_SUB`-based lead-time window selection in
 * `pageDueForMaterialization()`, the raw-SQL idempotence guard in
 * `reserveRun()`, and the run/occurrence state transitions.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineInterventionRecurrenceAdapter::class)]
final class DoctrineInterventionRecurrenceAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655440001';

  private const string TEMPLATE_ID = '660e8400-e29b-41d4-a716-446655440002';

  private const string OWNER_USER_ID = '660e8400-e29b-41d4-a716-446655449000';

  private const string INTERVENTION_ID = '660e8400-e29b-41d4-a716-446655440099';

  private const string MISSING_ID = '660e8400-e29b-41d4-a716-4466554400ff';

  private EntityManagerInterface $entityManager;

  private DoctrineInterventionRecurrenceAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var DoctrineInterventionRecurrenceAdapter $adapter */
    $adapter = static::getContainer()->get(DoctrineInterventionRecurrenceAdapter::class);
    $this->adapter = $adapter;

    $this->createOrganization();
    $this->createTemplate();
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
  public function testCreatePersistsRecurrenceReadableViaFind(): void
  {
    $anchor = new DateTimeImmutable('2026-01-01 00:00:00');
    $next = new DateTimeImmutable('2026-02-01 09:00:00');
    $end = new DateTimeImmutable('2026-12-31 00:00:00');

    $view = $this->adapter->create(
      self::ORGANIZATION_ID,
      self::TEMPLATE_ID,
      'Quarterly extinguisher check',
      'site-42',
      'resp-7',
      'quarterly',
      2,
      $anchor,
      'Europe/Paris',
      10,
      $next,
      $end,
    );
    $this->entityManager->clear();

    self::assertSame(self::ORGANIZATION_ID, $view->organizationId);
    self::assertSame(self::TEMPLATE_ID, $view->templateId);
    self::assertSame('Quarterly extinguisher check', $view->name);
    self::assertSame('site-42', $view->siteId);
    self::assertSame('resp-7', $view->responsibleId);
    self::assertSame('quarterly', $view->frequency);
    self::assertSame(2, $view->interval);
    self::assertSame('Europe/Paris', $view->timezone);
    self::assertSame(10, $view->leadTimeDays);
    self::assertTrue($view->isActive);
    self::assertNull($view->lastMaterializedAt);
    self::assertSame('2026-02-01 09:00:00', $view->nextOccurrenceAt->format('Y-m-d H:i:s'));
    self::assertSame('2026-12-31 00:00:00', $view->endAt?->format('Y-m-d H:i:s'));

    $found = $this->adapter->find($view->id);
    self::assertNotNull($found);
    self::assertSame($view->id, $found->id);
    self::assertSame('Quarterly extinguisher check', $found->name);
  }

  #[Test]
  public function testCreateThrowsWhenOrganizationIsMissing(): void
  {
    $this->expectException(InterventionNotFoundException::class);

    $this->adapter->create(
      self::MISSING_ID,
      self::TEMPLATE_ID,
      'Orphan',
      null,
      null,
      'monthly',
      1,
      new DateTimeImmutable('2026-01-01 00:00:00'),
      'UTC',
      7,
      new DateTimeImmutable('2026-02-01 00:00:00'),
      null,
    );
  }

  #[Test]
  public function testCreateThrowsWhenTemplateIsMissing(): void
  {
    $this->expectException(InterventionNotFoundException::class);

    $this->adapter->create(
      self::ORGANIZATION_ID,
      self::MISSING_ID,
      'Orphan',
      null,
      null,
      'monthly',
      1,
      new DateTimeImmutable('2026-01-01 00:00:00'),
      'UTC',
      7,
      new DateTimeImmutable('2026-02-01 00:00:00'),
      null,
    );
  }

  #[Test]
  public function testFindReturnsNullWhenAbsent(): void
  {
    self::assertNull($this->adapter->find(self::MISSING_ID));
  }

  #[Test]
  public function testUpdateAppliesFlaggedFields(): void
  {
    $recurrenceId = $this->persistRecurrence('rec-update', 'Original', true, new DateTimeImmutable('2026-03-01 08:00:00'));

    $updated = $this->adapter->update(
      $recurrenceId,
      'Renamed',
      'site-9',
      'resp-3',
      'weekly',
      4,
      new DateTimeImmutable('2026-04-01 00:00:00'),
      'America/New_York',
      21,
      new DateTimeImmutable('2026-05-01 07:30:00'),
      new DateTimeImmutable('2027-01-01 00:00:00'),
      false,
      true,
      true,
      true,
      true,
      true,
      true,
      true,
      true,
      true,
      true,
      true,
    );
    $this->entityManager->clear();

    self::assertSame('Renamed', $updated->name);
    self::assertSame('site-9', $updated->siteId);
    self::assertSame('resp-3', $updated->responsibleId);
    self::assertSame('weekly', $updated->frequency);
    self::assertSame(4, $updated->interval);
    self::assertSame('2026-04-01 00:00:00', $updated->anchorDate->format('Y-m-d H:i:s'));
    self::assertSame('America/New_York', $updated->timezone);
    self::assertSame(21, $updated->leadTimeDays);
    self::assertSame('2026-05-01 07:30:00', $updated->nextOccurrenceAt->format('Y-m-d H:i:s'));
    self::assertSame('2027-01-01 00:00:00', $updated->endAt?->format('Y-m-d H:i:s'));
    self::assertFalse($updated->isActive);

    $reloaded = $this->adapter->find($recurrenceId);
    self::assertNotNull($reloaded);
    self::assertSame('Renamed', $reloaded->name);
    self::assertFalse($reloaded->isActive);
  }

  #[Test]
  public function testUpdateIgnoresUnflaggedFields(): void
  {
    $recurrenceId = $this->persistRecurrence('rec-noop', 'Untouched', true, new DateTimeImmutable('2026-03-01 08:00:00'));

    $updated = $this->adapter->update(
      $recurrenceId,
      'Ignored',
      'ignored-site',
      'ignored-resp',
      'annual',
      99,
      new DateTimeImmutable('2030-01-01 00:00:00'),
      'Asia/Tokyo',
      365,
      new DateTimeImmutable('2030-02-01 00:00:00'),
      new DateTimeImmutable('2030-03-01 00:00:00'),
      false,
      false,
      false,
      false,
      false,
      false,
      false,
      false,
      false,
      false,
      false,
      false,
    );
    $this->entityManager->clear();

    self::assertSame('Untouched', $updated->name);
    self::assertNull($updated->siteId);
    self::assertNull($updated->responsibleId);
    self::assertSame('monthly', $updated->frequency);
    self::assertSame(1, $updated->interval);
    self::assertSame('UTC', $updated->timezone);
    self::assertSame(7, $updated->leadTimeDays);
    self::assertNull($updated->endAt);
    self::assertTrue($updated->isActive);
  }

  #[Test]
  public function testUpdateClearsNullableFieldsAndKeepsNullValuedRequiredFields(): void
  {
    $recurrenceId = $this->persistRecurrence(
      'rec-clear',
      'Keep me',
      true,
      new DateTimeImmutable('2026-03-01 08:00:00'),
      7,
      new DateTimeImmutable('2026-09-01 00:00:00'),
      'site-x',
      'resp-x',
    );

    $updated = $this->adapter->update(
      $recurrenceId,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      true,
      true,
      true,
      false,
      false,
      false,
      false,
      false,
      false,
      true,
      true,
    );
    $this->entityManager->clear();

    // Name kept: hasName is true, but the value is null so no change applies.
    self::assertSame('Keep me', $updated->name);
    // Nullable overrides explicitly cleared.
    self::assertNull($updated->siteId);
    self::assertNull($updated->responsibleId);
    self::assertNull($updated->endAt);
    // isActive kept: hasIsActive is true, but the value is null.
    self::assertTrue($updated->isActive);
  }

  #[Test]
  public function testUpdateThrowsWhenMissing(): void
  {
    $this->expectException(InterventionNotFoundException::class);

    $this->adapter->update(
      self::MISSING_ID,
      'x',
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      true,
      false,
      false,
      false,
      false,
      false,
      false,
      false,
      false,
      false,
      false,
    );
  }

  #[Test]
  public function testDeleteRemovesTheRecurrence(): void
  {
    $recurrenceId = $this->persistRecurrence('rec-delete', 'Doomed', true, new DateTimeImmutable('2026-03-01 08:00:00'));

    $this->adapter->delete($recurrenceId);
    $this->entityManager->clear();

    self::assertNull($this->adapter->find($recurrenceId));
  }

  #[Test]
  public function testDeleteThrowsWhenMissing(): void
  {
    $this->expectException(InterventionNotFoundException::class);
    $this->adapter->delete(self::MISSING_ID);
  }

  #[Test]
  public function testListReturnsOrganizationRecurrencesOrderedByName(): void
  {
    $this->persistRecurrence('rec-c', 'Charlie', true, new DateTimeImmutable('2026-03-01 08:00:00'));
    $this->persistRecurrence('rec-a', 'Alpha', true, new DateTimeImmutable('2026-03-01 08:00:00'));
    $this->persistRecurrence('rec-b', 'Bravo', true, new DateTimeImmutable('2026-03-01 08:00:00'));

    $page = $this->adapter->list(self::ORGANIZATION_ID, 1, 20);

    self::assertSame(3, $page->total);
    self::assertSame(1, $page->page);
    self::assertSame(20, $page->itemsPerPage);
    $names = array_map(static fn ($item): string => $item->name, $page->items);
    self::assertSame(['Alpha', 'Bravo', 'Charlie'], $names);
  }

  #[Test]
  public function testListFiltersByActiveState(): void
  {
    $this->persistRecurrence('rec-on', 'Active one', true, new DateTimeImmutable('2026-03-01 08:00:00'));
    $this->persistRecurrence('rec-off', 'Inactive one', false, new DateTimeImmutable('2026-03-01 08:00:00'));

    $activeOnly = $this->adapter->list(self::ORGANIZATION_ID, 1, 20, true);
    self::assertSame(1, $activeOnly->total);
    self::assertSame('Active one', $activeOnly->items[0]->name);

    $inactiveOnly = $this->adapter->list(self::ORGANIZATION_ID, 1, 20, false);
    self::assertSame(1, $inactiveOnly->total);
    self::assertSame('Inactive one', $inactiveOnly->items[0]->name);
  }

  #[Test]
  public function testListClampsPaginationBounds(): void
  {
    $this->persistRecurrence('rec-p1', 'Alpha', true, new DateTimeImmutable('2026-03-01 08:00:00'));
    $this->persistRecurrence('rec-p2', 'Bravo', true, new DateTimeImmutable('2026-03-01 08:00:00'));

    // page and itemsPerPage below the floor are clamped up to 1.
    $floor = $this->adapter->list(self::ORGANIZATION_ID, 0, 0);
    self::assertSame(1, $floor->page);
    self::assertSame(1, $floor->itemsPerPage);
    self::assertCount(1, $floor->items);
    self::assertSame(2, $floor->total);

    // itemsPerPage above the ceiling is clamped down to 100.
    $ceiling = $this->adapter->list(self::ORGANIZATION_ID, 1, 500);
    self::assertSame(100, $ceiling->itemsPerPage);
    self::assertCount(2, $ceiling->items);
  }

  #[Test]
  public function testPageDueForMaterializationSelectsOnlyActiveInWindowRecurrences(): void
  {
    $now = new DateTimeImmutable('2026-06-15 12:00:00');

    // Due: window opens 10 days before the occurrence, now is 5 days before it.
    $this->persistRecurrence('rec-due', 'Due', true, $now->modify('+5 days'), 10);
    // Not due yet: occurrence is 30 days away, window only opens 10 days before.
    $this->persistRecurrence('rec-future', 'Future', true, $now->modify('+30 days'), 10);
    // In window but inactive: must be excluded.
    $this->persistRecurrence('rec-dead', 'Dead', false, $now->modify('+1 day'), 10);
    // In window with an end date still ahead of the occurrence: still selected.
    $this->persistRecurrence('rec-ending', 'Ending', true, $now->modify('+1 day'), 10, $now->modify('+1 year'));

    $page = $this->adapter->pageDueForMaterialization($now, 50, 0);

    self::assertSame(0, $page->page);
    self::assertSame(50, $page->itemsPerPage);
    $ids = array_map(static fn ($item): string => $item->id, $page->items);
    self::assertContains('rec-due', $ids);
    self::assertContains('rec-ending', $ids);
    self::assertNotContains('rec-future', $ids);
    self::assertNotContains('rec-dead', $ids);
  }

  #[Test]
  public function testReserveRunClaimsOccurrenceOnceThenReturnsNullOnDuplicate(): void
  {
    $recurrenceId = $this->persistRecurrence('rec-run', 'Runnable', true, new DateTimeImmutable('2026-06-12 09:00:00'));
    $occurrence = new DateTimeImmutable('2026-06-15 10:00:00');

    $runId = $this->adapter->reserveRun($recurrenceId, $occurrence);
    self::assertNotNull($runId);

    // Same (recurrence, occurrence date): the ON CONFLICT DO NOTHING makes it
    // a no-op that reports the pair as already claimed.
    self::assertNull($this->adapter->reserveRun($recurrenceId, $occurrence));

    // The entity manager stays usable after the routine conflict, so a
    // different occurrence date claims a fresh run.
    $otherRunId = $this->adapter->reserveRun($recurrenceId, $occurrence->modify('+1 month'));
    self::assertNotNull($otherRunId);
    self::assertNotSame($runId, $otherRunId);

    $status = $this->entityManager->getConnection()->fetchOne(
      'SELECT status FROM intervention_recurrence_runs WHERE id = :id',
      ['id' => $runId],
    );
    self::assertSame('failed', $status);
  }

  #[Test]
  public function testReserveRunThrowsWhenRecurrenceMissing(): void
  {
    $this->expectException(InterventionNotFoundException::class);
    $this->adapter->reserveRun(self::MISSING_ID, new DateTimeImmutable('2026-06-15 10:00:00'));
  }

  #[Test]
  public function testMarkRunSucceededUpdatesStatusAndIntervention(): void
  {
    $recurrenceId = $this->persistRecurrence('rec-ok', 'Runnable', true, new DateTimeImmutable('2026-06-12 09:00:00'));
    $runId = $this->adapter->reserveRun($recurrenceId, new DateTimeImmutable('2026-06-15 10:00:00'));
    self::assertNotNull($runId);

    $this->adapter->markRunSucceeded($runId, self::INTERVENTION_ID);

    $row = $this->entityManager->getConnection()->fetchAssociative(
      'SELECT status, intervention_id, error FROM intervention_recurrence_runs WHERE id = :id',
      ['id' => $runId],
    );
    self::assertIsArray($row);
    self::assertSame('succeeded', $row['status']);
    self::assertSame(self::INTERVENTION_ID, $row['intervention_id']);
    self::assertNull($row['error']);
  }

  #[Test]
  public function testMarkRunFailedUpdatesStatusAndError(): void
  {
    $recurrenceId = $this->persistRecurrence('rec-ko', 'Runnable', true, new DateTimeImmutable('2026-06-12 09:00:00'));
    $runId = $this->adapter->reserveRun($recurrenceId, new DateTimeImmutable('2026-06-16 10:00:00'));
    self::assertNotNull($runId);

    $this->adapter->markRunFailed($runId, 'Template instantiation blew up.');

    $row = $this->entityManager->getConnection()->fetchAssociative(
      'SELECT status, error FROM intervention_recurrence_runs WHERE id = :id',
      ['id' => $runId],
    );
    self::assertIsArray($row);
    self::assertSame('failed', $row['status']);
    self::assertSame('Template instantiation blew up.', $row['error']);
  }

  #[Test]
  public function testMarkRunSucceededThrowsWhenRunMissing(): void
  {
    $this->expectException(InterventionNotFoundException::class);
    $this->adapter->markRunSucceeded(self::MISSING_ID, self::INTERVENTION_ID);
  }

  #[Test]
  public function testMarkRunFailedThrowsWhenRunMissing(): void
  {
    $this->expectException(InterventionNotFoundException::class);
    $this->adapter->markRunFailed(self::MISSING_ID, 'boom');
  }

  #[Test]
  public function testAdvanceNextOccurrenceUpdatesBothWithAndWithoutMaterializationInstant(): void
  {
    $recurrenceId = $this->persistRecurrence('rec-advance', 'Advancing', true, new DateTimeImmutable('2026-06-01 09:00:00'));

    $this->adapter->advanceNextOccurrence(
      $recurrenceId,
      new DateTimeImmutable('2026-07-01 09:00:00'),
      new DateTimeImmutable('2026-06-01 09:05:00'),
    );
    $this->entityManager->clear();

    $afterSuccess = $this->adapter->find($recurrenceId);
    self::assertNotNull($afterSuccess);
    self::assertSame('2026-07-01 09:00:00', $afterSuccess->nextOccurrenceAt->format('Y-m-d H:i:s'));
    self::assertSame('2026-06-01 09:05:00', $afterSuccess->lastMaterializedAt?->format('Y-m-d H:i:s'));

    // A subsequent advance without a materialization instant advances the
    // next occurrence but leaves lastMaterializedAt untouched.
    $this->adapter->advanceNextOccurrence(
      $recurrenceId,
      new DateTimeImmutable('2026-08-01 09:00:00'),
      null,
    );
    $this->entityManager->clear();

    $afterFailure = $this->adapter->find($recurrenceId);
    self::assertNotNull($afterFailure);
    self::assertSame('2026-08-01 09:00:00', $afterFailure->nextOccurrenceAt->format('Y-m-d H:i:s'));
    self::assertSame('2026-06-01 09:05:00', $afterFailure->lastMaterializedAt?->format('Y-m-d H:i:s'));
  }

  #[Test]
  public function testAdvanceNextOccurrenceThrowsWhenMissing(): void
  {
    $this->expectException(InterventionNotFoundException::class);
    $this->adapter->advanceNextOccurrence(self::MISSING_ID, new DateTimeImmutable('2026-07-01 09:00:00'), null);
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Recurrence Adapter Test';
    $organization->slug = 'recurrence-adapter-test';
    $organization->ownerUserId = self::OWNER_USER_ID;
    $organization->createdByUserId = self::OWNER_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createTemplate(): void
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $template = new InterventionTemplateRecord();
    $template->id = self::TEMPLATE_ID;
    $template->organization = $organization;
    $template->name = 'Fire safety audit';
    $template->type = 'inspection_campaign';
    $template->priority = 'normal';
    $template->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $template->updatedAt = $template->createdAt;
    $this->entityManager->persist($template);
  }

  private function persistRecurrence(
    string $id,
    string $name,
    bool $isActive,
    DateTimeImmutable $nextOccurrenceAt,
    int $leadTimeDays = 7,
    ?DateTimeImmutable $endAt = null,
    ?string $siteId = null,
    ?string $responsibleId = null,
  ): string {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    $template = $this->entityManager->getReference(InterventionTemplateRecord::class, self::TEMPLATE_ID);

    $record = new InterventionRecurrenceRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->template = $template;
    $record->name = $name;
    $record->siteId = $siteId;
    $record->responsibleId = $responsibleId;
    $record->frequency = 'monthly';
    $record->interval = 1;
    $record->anchorDate = new DateTimeImmutable('2026-01-01 00:00:00');
    $record->timezone = 'UTC';
    $record->leadTimeDays = $leadTimeDays;
    $record->nextOccurrenceAt = $nextOccurrenceAt;
    $record->isActive = $isActive;
    $record->endAt = $endAt;
    $record->createdAt = new DateTimeImmutable('2026-01-01 00:00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    return $id;
  }

  /**
   * Method cleanup.
   *
   * Deletes fixture rows in FK-safe order: runs and recurrences first (the
   * `template_id` FK is non-cascading — see
   * `InterventionRecurrenceRecord::$template`), then the template, then the
   * organization.
   */
  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM intervention_recurrence_runs WHERE recurrence_id IN '
      . '(SELECT id FROM intervention_recurrences WHERE organization_id = :organizationId)',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_recurrences WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_templates WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
