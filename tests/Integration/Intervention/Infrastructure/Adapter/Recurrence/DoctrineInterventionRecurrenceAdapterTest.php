<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Recurrence;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Recurrence\DoctrineInterventionRecurrenceAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecurrenceRecord, InterventionTemplateRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test DoctrineInterventionRecurrenceAdapterTest.
 *
 * Exercises the real DQL/DBAL behavior that is hard to trust from a mock:
 * the `DATE_SUB`-based lead-time window selection in
 * `pageDueForMaterialization()`, and the raw-SQL idempotence guard in
 * `reserveRun()`.
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

  private const string RECURRENCE_DUE_ID = '660e8400-e29b-41d4-a716-446655440010';

  private const string RECURRENCE_NOT_DUE_ID = '660e8400-e29b-41d4-a716-446655440011';

  private const string RECURRENCE_INACTIVE_ID = '660e8400-e29b-41d4-a716-446655440012';

  private const string RECURRENCE_PAST_END_ID = '660e8400-e29b-41d4-a716-446655440013';

  private const string RECURRENCE_FUTURE_END_ID = '660e8400-e29b-41d4-a716-446655440014';

  private const string RECURRENCE_RESERVE_ID = '660e8400-e29b-41d4-a716-446655440015';

  private EntityManagerInterface $entityManager;

  private DoctrineInterventionRecurrenceAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var UuidGeneratorPort $generator */
    $generator = static::getContainer()->get(UuidGeneratorPort::class);
    $this->adapter = new DoctrineInterventionRecurrenceAdapter($this->entityManager, new UuidFactory($generator));

    $this->createOrganization();
    $this->createTemplate();
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testPageDueForMaterializationSelectsOnlyRecurrencesWhoseLeadTimeWindowHasOpened(): void
  {
    $now = new DateTimeImmutable('2026-06-15T00:00:00+00:00');

    // Due: lead time opens the window 10 days before the occurrence, and
    // "now" is only 5 days before it (inside the window).
    $this->createRecurrence(self::RECURRENCE_DUE_ID, $now->modify('+5 days'), leadTimeDays: 10);
    // Not due yet: lead time window has not opened (occurrence is 30 days
    // away, lead time only 10 days).
    $this->createRecurrence(self::RECURRENCE_NOT_DUE_ID, $now->modify('+30 days'), leadTimeDays: 10);
    // Inactive: due by date, but must never be selected.
    $this->createRecurrence(self::RECURRENCE_INACTIVE_ID, $now->modify('+1 day'), leadTimeDays: 10, isActive: false);
    // endAt before the occurrence: must be excluded even though due.
    $this->createRecurrence(self::RECURRENCE_PAST_END_ID, $now->modify('+1 day'), leadTimeDays: 10, endAt: $now->modify('-1 day'));
    // endAt after the occurrence: still selected.
    $this->createRecurrence(self::RECURRENCE_FUTURE_END_ID, $now->modify('+1 day'), leadTimeDays: 10, endAt: $now->modify('+1 year'));

    $this->entityManager->flush();
    $this->entityManager->clear();

    $page = $this->adapter->pageDueForMaterialization($now, 100, 0);
    $ids = array_map(static fn ($view): string => $view->id, $page->items);

    self::assertContains(self::RECURRENCE_DUE_ID, $ids);
    self::assertContains(self::RECURRENCE_FUTURE_END_ID, $ids);
    self::assertNotContains(self::RECURRENCE_NOT_DUE_ID, $ids);
    self::assertNotContains(self::RECURRENCE_INACTIVE_ID, $ids);
    self::assertNotContains(self::RECURRENCE_PAST_END_ID, $ids);
  }

  #[Test]
  public function testReserveRunIsIdempotentPerRecurrenceAndOccurrenceDate(): void
  {
    $occurrenceAt = new DateTimeImmutable('2026-06-15T00:00:00+00:00');
    $this->createRecurrence(self::RECURRENCE_RESERVE_ID, $occurrenceAt, leadTimeDays: 10);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $firstRunId = $this->adapter->reserveRun(self::RECURRENCE_RESERVE_ID, $occurrenceAt);
    self::assertIsString($firstRunId);

    // Same recurrence, same occurrence instant: must be rejected by the
    // unique constraint and reported as an already-claimed reservation.
    $secondRunId = $this->adapter->reserveRun(self::RECURRENCE_RESERVE_ID, $occurrenceAt);
    self::assertNull($secondRunId);

    // The entity manager must remain fully usable after the constraint
    // violation (the whole point of using a raw DBAL statement instead of
    // the ORM's persist()/flush() for the reservation).
    $thirdRunId = $this->adapter->reserveRun(self::RECURRENCE_RESERVE_ID, $occurrenceAt->modify('+1 month'));
    self::assertIsString($thirdRunId);
    self::assertNotSame($firstRunId, $thirdRunId);
  }

  private function createRecurrence(
    string $id,
    DateTimeImmutable $nextOccurrenceAt,
    int $leadTimeDays,
    bool $isActive = true,
    ?DateTimeImmutable $endAt = null,
  ): InterventionRecurrenceRecord {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    $template = $this->entityManager->getReference(InterventionTemplateRecord::class, self::TEMPLATE_ID);

    $record = new InterventionRecurrenceRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->template = $template;
    $record->name = 'Recurrence ' . $id;
    $record->frequency = 'monthly';
    $record->interval = 1;
    $record->anchorDate = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->timezone = 'UTC';
    $record->leadTimeDays = $leadTimeDays;
    $record->nextOccurrenceAt = $nextOccurrenceAt;
    $record->isActive = $isActive;
    $record->endAt = $endAt;
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);

    return $record;
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Recurrence Adapter Test';
    $organization->slug = 'recurrence-adapter-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655449000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655449000';
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

  /**
   * Method cleanup.
   *
   * Deletes fixture rows in FK-safe order: recurrences and their runs first
   * (the `template_id` FK is RESTRICT, not cascade — see
   * `InterventionRecurrenceRecord::$template`), then the template, then the
   * organization.
   */
  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM intervention_recurrence_runs WHERE recurrence_id IN (SELECT id FROM intervention_recurrences WHERE organization_id = :organizationId)',
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
