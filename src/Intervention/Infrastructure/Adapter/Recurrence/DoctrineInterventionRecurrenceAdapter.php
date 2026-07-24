<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Recurrence\{InterventionRecurrencePage, InterventionRecurrenceView};
use Intervention\Application\Port\Outbound\InterventionRecurrencePort;
use Intervention\Domain\Exception\InterventionNotFoundException;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecurrenceRecord, InterventionRecurrenceRunRecord, InterventionTemplateRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Factory\UuidFactory;

use function array_map;
use function max;
use function min;

/**
 * Adapter DoctrineInterventionRecurrenceAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineInterventionRecurrenceAdapter implements InterventionRecurrencePort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param UuidFactory $uuidFactory the uuid factory value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region CRUD
  public function create(
    string $organizationId,
    string $templateId,
    string $name,
    ?string $siteId,
    ?string $responsibleId,
    string $frequency,
    int $interval,
    DateTimeImmutable $anchorDate,
    string $timezone,
    int $leadTimeDays,
    DateTimeImmutable $nextOccurrenceAt,
    ?DateTimeImmutable $endAt,
  ): InterventionRecurrenceView {
    $organization = $this->entityManager->find(OrganizationRecord::class, $organizationId);
    if (!$organization instanceof OrganizationRecord) {
      throw InterventionNotFoundException::withId($organizationId);
    }
    $template = $this->entityManager->find(InterventionTemplateRecord::class, $templateId);
    if (!$template instanceof InterventionTemplateRecord) {
      throw InterventionNotFoundException::withId($templateId);
    }

    $now = new DateTimeImmutable();
    $record = new InterventionRecurrenceRecord();
    $record->id = $this->uuidFactory->generateRaw();
    $record->organization = $organization;
    $record->template = $template;
    $record->name = $name;
    $record->siteId = $siteId;
    $record->responsibleId = $responsibleId;
    $record->frequency = $frequency;
    $record->interval = $interval;
    $record->anchorDate = $anchorDate;
    $record->timezone = $timezone;
    $record->leadTimeDays = $leadTimeDays;
    $record->nextOccurrenceAt = $nextOccurrenceAt;
    $record->endAt = $endAt;
    $record->createdAt = $now;
    $record->updatedAt = $now;

    $this->entityManager->persist($record);
    $this->entityManager->flush();

    return $this->view($record);
  }

  public function update(
    string $id,
    ?string $name,
    ?string $siteId,
    ?string $responsibleId,
    ?string $frequency,
    ?int $interval,
    ?DateTimeImmutable $anchorDate,
    ?string $timezone,
    ?int $leadTimeDays,
    ?DateTimeImmutable $nextOccurrenceAt,
    ?DateTimeImmutable $endAt,
    ?bool $isActive,
    bool $hasName,
    bool $hasSiteId,
    bool $hasResponsibleId,
    bool $hasFrequency,
    bool $hasInterval,
    bool $hasAnchorDate,
    bool $hasTimezone,
    bool $hasLeadTimeDays,
    bool $hasNextOccurrenceAt,
    bool $hasEndAt,
    bool $hasIsActive,
  ): InterventionRecurrenceView {
    $record = $this->entityManager->find(InterventionRecurrenceRecord::class, $id);
    if (!$record instanceof InterventionRecurrenceRecord) {
      throw InterventionNotFoundException::withId($id);
    }

    if ($hasName && null !== $name) {
      $record->name = $name;
    }
    if ($hasSiteId) {
      $record->siteId = $siteId;
    }
    if ($hasResponsibleId) {
      $record->responsibleId = $responsibleId;
    }
    if ($hasFrequency && null !== $frequency) {
      $record->frequency = $frequency;
    }
    if ($hasInterval && null !== $interval) {
      $record->interval = $interval;
    }
    if ($hasAnchorDate && null !== $anchorDate) {
      $record->anchorDate = $anchorDate;
    }
    if ($hasTimezone && null !== $timezone) {
      $record->timezone = $timezone;
    }
    if ($hasLeadTimeDays && null !== $leadTimeDays) {
      $record->leadTimeDays = $leadTimeDays;
    }
    if ($hasNextOccurrenceAt && null !== $nextOccurrenceAt) {
      $record->nextOccurrenceAt = $nextOccurrenceAt;
    }
    if ($hasEndAt) {
      $record->endAt = $endAt;
    }
    if ($hasIsActive && null !== $isActive) {
      $record->isActive = $isActive;
    }
    $record->updatedAt = new DateTimeImmutable();

    $this->entityManager->flush();

    return $this->view($record);
  }

  public function delete(string $id): void
  {
    $record = $this->entityManager->find(InterventionRecurrenceRecord::class, $id);
    if (!$record instanceof InterventionRecurrenceRecord) {
      throw InterventionNotFoundException::withId($id);
    }
    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }

  public function find(string $id): ?InterventionRecurrenceView
  {
    $record = $this->entityManager->find(InterventionRecurrenceRecord::class, $id);

    return $record instanceof InterventionRecurrenceRecord ? $this->view($record) : null;
  }

  public function list(string $organizationId, int $page, int $itemsPerPage, ?bool $isActive = null): InterventionRecurrencePage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $qb = $this->entityManager->createQueryBuilder()
      ->select('r')
      ->from(InterventionRecurrenceRecord::class, 'r')
      ->where('r.organization = :organization')
      ->setParameter('organization', $organization)
      ->orderBy('r.name', 'ASC');

    if (null !== $isActive) {
      $qb->andWhere('r.isActive = :isActive')->setParameter('isActive', $isActive);
    }

    $total = (int) (clone $qb)
      ->select('COUNT(r.id)')
      ->resetDQLPart('orderBy')
      ->getQuery()
      ->getSingleScalarResult();

    /** @var list<InterventionRecurrenceRecord> $records */
    $records = $qb
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new InterventionRecurrencePage(array_map($this->view(...), $records), $page, $itemsPerPage, $total);
  }
  // #endregion

  // #region Materialization
  public function pageDueForMaterialization(DateTimeImmutable $now, int $limit, int $offset): InterventionRecurrencePage
  {
    $limit = max(1, $limit);
    $offset = max(0, $offset);

    $qb = $this->entityManager->createQueryBuilder()
      ->select('r')
      ->from(InterventionRecurrenceRecord::class, 'r')
      ->where('r.isActive = true')
      ->andWhere("DATE_SUB(r.nextOccurrenceAt, r.leadTimeDays, 'day') <= :now")
      ->andWhere('r.endAt IS NULL OR r.nextOccurrenceAt <= r.endAt')
      ->setParameter('now', $now)
      ->orderBy('r.id', 'ASC')
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    /** @var list<InterventionRecurrenceRecord> $records */
    $records = $qb->getQuery()->getResult();

    return new InterventionRecurrencePage(array_map($this->view(...), $records), 0, $limit, 0);
  }

  public function reserveRun(string $recurrenceId, DateTimeImmutable $occurrenceDate): ?string
  {
    $recurrence = $this->entityManager->find(InterventionRecurrenceRecord::class, $recurrenceId);
    if (!$recurrence instanceof InterventionRecurrenceRecord) {
      throw InterventionNotFoundException::withId($recurrenceId);
    }

    $localOccurrenceDate = new DateTimeImmutable(
      $occurrenceDate->setTimezone(new DateTimeZone($recurrence->timezone))->format('Y-m-d'),
    );
    $runId = $this->uuidFactory->generateRaw();

    // A raw DBAL statement — not the ORM's persist()/flush() — is used
    // deliberately: the sweep processes many recurrences per request, and a
    // unique-constraint violation during an ORM flush() closes the
    // EntityManager for the rest of the request (see
    // DoctrinePublicationAdapter::markFailed()'s isOpen() fallback for the
    // same concern). A duplicate occurrence claim is an expected, routine
    // outcome, so ON CONFLICT DO NOTHING makes it an in-DB no-op returning zero
    // affected rows: no exception is raised, so it never touches the ORM's unit
    // of work and never aborts the surrounding transaction (catching the
    // violation instead poisons it on PostgreSQL).
    $inserted = $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO intervention_recurrence_runs (id, recurrence_id, occurrence_date, status, error, created_at) '
      . 'VALUES (:id, :recurrenceId, :occurrenceDate, :status, :error, :createdAt) '
      . 'ON CONFLICT DO NOTHING',
      [
        'id' => $runId,
        'recurrenceId' => $recurrenceId,
        'occurrenceDate' => $localOccurrenceDate,
        'status' => 'failed',
        'error' => 'Materialization reserved but not yet completed.',
        'createdAt' => new DateTimeImmutable(),
      ],
      ['occurrenceDate' => 'date_immutable', 'createdAt' => 'datetime_immutable'],
    );

    if (0 === $inserted) {
      return null;
    }

    return $runId;
  }

  public function markRunSucceeded(string $runId, string $interventionId): void
  {
    $run = $this->entityManager->find(InterventionRecurrenceRunRecord::class, $runId);
    if (!$run instanceof InterventionRecurrenceRunRecord) {
      throw InterventionNotFoundException::withId($runId);
    }

    $run->status = 'succeeded';
    $run->interventionId = $interventionId;
    $run->error = null;
    $this->entityManager->flush();
  }

  public function markRunFailed(string $runId, string $error): void
  {
    $run = $this->entityManager->find(InterventionRecurrenceRunRecord::class, $runId);
    if (!$run instanceof InterventionRecurrenceRunRecord) {
      throw InterventionNotFoundException::withId($runId);
    }

    $run->status = 'failed';
    $run->error = $error;
    $this->entityManager->flush();
  }

  public function advanceNextOccurrence(string $recurrenceId, DateTimeImmutable $nextOccurrenceAt, ?DateTimeImmutable $lastMaterializedAt): void
  {
    $record = $this->entityManager->find(InterventionRecurrenceRecord::class, $recurrenceId);
    if (!$record instanceof InterventionRecurrenceRecord) {
      throw InterventionNotFoundException::withId($recurrenceId);
    }

    $record->nextOccurrenceAt = $nextOccurrenceAt;
    if (null !== $lastMaterializedAt) {
      $record->lastMaterializedAt = $lastMaterializedAt;
    }
    $record->updatedAt = new DateTimeImmutable();

    $this->entityManager->flush();
  }
  // #endregion

  // #region Mapping
  /**
   * Method view.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceRecord $record the record value
   *
   * @return InterventionRecurrenceView the recurrence view result
   */
  private function view(InterventionRecurrenceRecord $record): InterventionRecurrenceView
  {
    return new InterventionRecurrenceView(
      $record->id,
      $this->organizationId($record),
      $this->templateId($record),
      $record->name,
      $record->siteId,
      $record->responsibleId,
      $record->frequency,
      $record->interval,
      $record->anchorDate,
      $record->timezone,
      $record->leadTimeDays,
      $record->nextOccurrenceAt,
      $record->lastMaterializedAt,
      $record->isActive,
      $record->endAt,
      $record->createdAt,
      $record->updatedAt,
    );
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceRecord $record the record value
   *
   * @return string the organization id result
   */
  private function organizationId(InterventionRecurrenceRecord $record): string
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new InterventionNotFoundException('Intervention recurrence organization is missing.');
    }

    return $record->organization->id;
  }

  /**
   * Method templateId.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceRecord $record the record value
   *
   * @return string the template id result
   */
  private function templateId(InterventionRecurrenceRecord $record): string
  {
    if (!$record->template instanceof InterventionTemplateRecord) {
      throw new InterventionNotFoundException('Intervention recurrence template is missing.');
    }

    return $record->template->id;
  }
  // #endregion
}
