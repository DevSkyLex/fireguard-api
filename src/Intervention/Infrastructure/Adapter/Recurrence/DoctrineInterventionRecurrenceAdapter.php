<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Recurrence\{InterventionRecurrenceCreateRequest, InterventionRecurrencePage, InterventionRecurrenceUpdateRequest, InterventionRecurrenceView};
use Intervention\Application\Port\Outbound\InterventionRecurrencePort;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecurrenceRecord, InterventionRecurrenceRunRecord, InterventionTemplateRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Factory\UuidFactory;

use function array_map;
use function max;
use function min;

/**
 * Class DoctrineInterventionRecurrenceAdapter
 *
 * Persists recurrence schedules and reserves their materialization runs in Doctrine.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineInterventionRecurrenceAdapter implements InterventionRecurrencePort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies the explicitly wired entity manager and identifier factory.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param UuidFactory $uuidFactory the uuid factory value
   *
   * @return void
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region CRUD
  /**
   * Method create
   *
   * Creates a recurrence record from the validated request and returns its view.
   *
   * @access public
   *
   * @param InterventionRecurrenceCreateRequest $request validated recurrence data
   *
   * @return InterventionRecurrenceView created recurrence view
   *
   * @throws InterventionNotFoundException when the organization or template does not exist
   */
  public function create(InterventionRecurrenceCreateRequest $request): InterventionRecurrenceView
  {
    $organization = $this->entityManager->find(OrganizationRecord::class, $request->organizationId);
    if (!$organization instanceof OrganizationRecord) {
      throw InterventionNotFoundException::withId($request->organizationId);
    }
    $template = $this->entityManager->find(InterventionTemplateRecord::class, $request->templateId);
    if (!$template instanceof InterventionTemplateRecord) {
      throw InterventionNotFoundException::withId($request->templateId);
    }

    $now = new DateTimeImmutable();
    $record = new InterventionRecurrenceRecord();
    $record->id = $this->uuidFactory->generateRaw();
    $record->organization = $organization;
    $record->template = $template;
    $record->name = $request->name;
    $record->siteId = $request->siteId;
    $record->responsibleId = $request->responsibleId;
    $record->frequency = $request->schedule->frequency;
    $record->interval = $request->schedule->interval;
    $record->anchorDate = $request->schedule->anchorDate;
    $record->timezone = $request->schedule->timezone;
    $record->leadTimeDays = $request->schedule->leadTimeDays;
    $record->nextOccurrenceAt = $request->schedule->nextOccurrenceAt;
    $record->endAt = $request->schedule->endAt;
    $record->createdAt = $now;
    $record->updatedAt = $now;

    $this->entityManager->persist($record);
    $this->entityManager->flush();

    return $this->view($record);
  }

  /**
   * Method update
   *
   * Applies only request fields marked present and returns the updated recurrence view.
   *
   * @access public
   *
   * @param InterventionRecurrenceUpdateRequest $request recurrence identifier and merge-patch fields
   *
   * @return InterventionRecurrenceView updated recurrence view
   *
   * @throws InterventionNotFoundException when the recurrence does not exist
   */
  public function update(InterventionRecurrenceUpdateRequest $request): InterventionRecurrenceView
  {
    $record = $this->entityManager->find(InterventionRecurrenceRecord::class, $request->id);
    if (!$record instanceof InterventionRecurrenceRecord) {
      throw InterventionNotFoundException::withId($request->id);
    }

    $this->updateIdentity(
      $record,
      $request->identity->name,
      $request->identity->siteId,
      $request->identity->responsibleId,
      $request->identity->hasName,
      $request->identity->hasSiteId,
      $request->identity->hasResponsibleId,
    );
    $this->updateCadence(
      $record,
      $request->cadence->frequency,
      $request->cadence->interval,
      $request->cadence->anchorDate,
      $request->cadence->hasFrequency,
      $request->cadence->hasInterval,
      $request->cadence->hasAnchorDate,
    );
    $this->updateSchedule(
      $record,
      $request->schedule->timezone,
      $request->schedule->leadTimeDays,
      $request->schedule->nextOccurrenceAt,
      $request->schedule->hasTimezone,
      $request->schedule->hasLeadTimeDays,
      $request->schedule->hasNextOccurrenceAt,
    );
    $this->updateLifecycle(
      $record,
      $request->lifecycle->endAt,
      $request->lifecycle->isActive,
      $request->lifecycle->hasEndAt,
      $request->lifecycle->hasIsActive,
    );
    $record->updatedAt = new DateTimeImmutable();

    $this->entityManager->flush();

    return $this->view($record);
  }

  /**
   * Method delete
   *
   * Removes an existing recurrence record.
   *
   * @access public
   *
   * @param string $id recurrence identifier
   *
   * @return void
   *
   * @throws InterventionNotFoundException when the recurrence does not exist
   */
  public function delete(string $id): void
  {
    $record = $this->entityManager->find(InterventionRecurrenceRecord::class, $id);
    if (!$record instanceof InterventionRecurrenceRecord) {
      throw InterventionNotFoundException::withId($id);
    }
    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }

  /**
   * Method find
   *
   * Loads a recurrence by identifier and maps it to its application view.
   *
   * @access public
   *
   * @param string $id recurrence identifier
   *
   * @return InterventionRecurrenceView|null recurrence view, or null when absent
   */
  public function find(string $id): ?InterventionRecurrenceView
  {
    $record = $this->entityManager->find(InterventionRecurrenceRecord::class, $id);

    return $record instanceof InterventionRecurrenceRecord ? $this->view($record) : null;
  }

  /**
   * Method list
   *
   * Returns a bounded page of an organization’s recurrences, optionally filtered by active state.
   *
   * @access public
   *
   * @param string $organizationId organization identifier
   * @param int $page one-based page number
   * @param int $itemsPerPage requested page size, capped at 100
   * @param bool|null $isActive optional active-state filter
   *
   * @return InterventionRecurrencePage recurrence page and count
   */
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
  /**
   * Method pageDueForMaterialization
   *
   * Pages active recurrences whose lead-time window has opened and whose end date allows the next occurrence.
   *
   * @access public
   *
   * @param DateTimeImmutable $now cutoff instant for lead-time eligibility
   * @param int $limit maximum number of recurrences returned
   * @param ?string $afterId last processed identifier, null for the first page
   *
   * @return InterventionRecurrencePage due recurrence page
   */
  public function pageDueForMaterialization(DateTimeImmutable $now, int $limit, ?string $afterId = null): InterventionRecurrencePage
  {
    $limit = max(1, $limit);

    $qb = $this->entityManager->createQueryBuilder()
      ->select('r')
      ->from(InterventionRecurrenceRecord::class, 'r')
      ->where('r.isActive = true')
      ->andWhere("DATE_SUB(r.nextOccurrenceAt, r.leadTimeDays, 'day') <= :now")
      ->andWhere('r.endAt IS NULL OR r.nextOccurrenceAt <= r.endAt')
      ->setParameter('now', $now)
      ->orderBy('r.id', 'ASC')
      ->setMaxResults($limit);

    if (null !== $afterId) {
      $qb->andWhere('r.id > :afterId')->setParameter('afterId', $afterId);
    }

    /** @var list<InterventionRecurrenceRecord> $records */
    $records = $qb->getQuery()->getResult();

    return new InterventionRecurrencePage(array_map($this->view(...), $records), 0, $limit, 0);
  }

  /**
   * Method reserveRun
   *
   * Inserts a placeholder run under the recurrence/date uniqueness guard, returning null for an existing claim.
   *
   * @access public
   *
   * @param string $recurrenceId recurrence identifier
   * @param DateTimeImmutable $occurrenceDate occurrence date to reserve
   *
   * @return string|null new run identifier, or null when already reserved
   *
   * @throws InterventionNotFoundException when the recurrence does not exist
   */
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

  /**
   * Method lockReservedRun
   *
   * Reads an unresolved legacy marker under row locks without automatically creating another draft.
   *
   * @access public
   *
   * @param string $runId run identifier selected by the operator
   *
   * @return ?InterventionRecurrenceView the matching scheduled occurrence, null for resolved runs
   */
  public function lockReservedRun(string $runId): ?InterventionRecurrenceView
  {
    $row = $this->entityManager->getConnection()->fetchAssociative(
      'SELECT run.recurrence_id, run.occurrence_date, run.status, run.error, run.intervention_id '
      . 'FROM intervention_recurrence_runs run JOIN intervention_recurrences recurrence ON recurrence.id = run.recurrence_id '
      . 'WHERE run.id = :id FOR UPDATE OF run, recurrence',
      ['id' => $runId],
    );
    if (false === $row) {
      throw InterventionNotFoundException::withId($runId);
    }
    if ('failed' !== $row['status'] || 'Materialization reserved but not yet completed.' !== $row['error'] || null !== $row['intervention_id']) {
      return null;
    }
    $recurrence = $this->entityManager->find(InterventionRecurrenceRecord::class, $row['recurrence_id']);
    if (!$recurrence instanceof InterventionRecurrenceRecord) {
      throw InterventionNotFoundException::withId($runId);
    }
    $this->entityManager->refresh($recurrence);
    $scheduledDate = $recurrence->nextOccurrenceAt->setTimezone(new DateTimeZone($recurrence->timezone))->format('Y-m-d');
    if ($scheduledDate !== $row['occurrence_date']) {
      throw new InterventionConflictException('The legacy reservation no longer matches the scheduled occurrence; reconcile it before recovery.');
    }

    return $this->view($recurrence);
  }

  /**
   * Method markRunSucceeded
   *
   * Records the created intervention identifier and successful status for a run.
   *
   * @access public
   *
   * @param string $runId materialization run identifier
   * @param string $interventionId created intervention identifier
   *
   * @return void
   *
   * @throws InterventionNotFoundException when the run does not exist
   */
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

  /**
   * Method markRunFailed
   *
   * Records the failure status and reason for a materialization run.
   *
   * @access public
   *
   * @param string $runId materialization run identifier
   * @param string $error failure reason stored for the run
   *
   * @return void
   *
   * @throws InterventionNotFoundException when the run does not exist
   */
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

  /**
   * Method advanceNextOccurrence
   *
   * Updates the scheduled occurrence and sets the last-materialized instant only when supplied.
   *
   * @access public
   *
   * @param string $recurrenceId recurrence identifier
   * @param DateTimeImmutable $nextOccurrenceAt recomputed next occurrence
   * @param DateTimeImmutable|null $lastMaterializedAt successful materialization instant, or null
   *
   * @return void
   *
   * @throws InterventionNotFoundException when the recurrence does not exist
   */
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

  /**
   * Method updateIdentity
   *
   * Applies only present name, site, and responsible fields from a merge-patch request.
   *
   * @access private
   *
   * @param InterventionRecurrenceRecord $record recurrence record being updated
   * @param string|null $name replacement name when present
   * @param string|null $siteId replacement site identifier when present
   * @param string|null $responsibleId replacement responsible identifier when present
   * @param bool $hasName whether the request supplied the name field
   * @param bool $hasSiteId whether the request supplied the site field
   * @param bool $hasResponsibleId whether the request supplied the responsible field
   *
   * @return void
   */
  private function updateIdentity(
    InterventionRecurrenceRecord $record,
    ?string $name,
    ?string $siteId,
    ?string $responsibleId,
    bool $hasName,
    bool $hasSiteId,
    bool $hasResponsibleId,
  ): void {
    if ($hasName && null !== $name) {
      $record->name = $name;
    }
    if ($hasSiteId) {
      $record->siteId = $siteId;
    }
    if ($hasResponsibleId) {
      $record->responsibleId = $responsibleId;
    }
  }

  /**
   * Method updateCadence
   *
   * Applies present frequency, interval, and anchor-date fields from a merge-patch request.
   *
   * @access private
   *
   * @param InterventionRecurrenceRecord $record recurrence record being updated
   * @param string|null $frequency replacement frequency when present
   * @param int|null $interval replacement interval when present
   * @param DateTimeImmutable|null $anchorDate replacement anchor date when present
   * @param bool $hasFrequency whether the request supplied the frequency field
   * @param bool $hasInterval whether the request supplied the interval field
   * @param bool $hasAnchorDate whether the request supplied the anchor date field
   *
   * @return void
   */
  private function updateCadence(
    InterventionRecurrenceRecord $record,
    ?string $frequency,
    ?int $interval,
    ?DateTimeImmutable $anchorDate,
    bool $hasFrequency,
    bool $hasInterval,
    bool $hasAnchorDate,
  ): void {
    if ($hasFrequency && null !== $frequency) {
      $record->frequency = $frequency;
    }
    if ($hasInterval && null !== $interval) {
      $record->interval = $interval;
    }
    if ($hasAnchorDate && null !== $anchorDate) {
      $record->anchorDate = $anchorDate;
    }
  }

  /**
   * Method updateSchedule
   *
   * Applies present timezone, lead-time, and next-occurrence fields from a merge-patch request.
   *
   * @access private
   *
   * @param InterventionRecurrenceRecord $record recurrence record being updated
   * @param string|null $timezone replacement timezone when present
   * @param int|null $leadTimeDays replacement lead-time days when present
   * @param DateTimeImmutable|null $nextOccurrenceAt replacement next occurrence when present
   * @param bool $hasTimezone whether the request supplied the timezone field
   * @param bool $hasLeadTimeDays whether the request supplied the lead-time field
   * @param bool $hasNextOccurrenceAt whether the request supplied the next-occurrence field
   *
   * @return void
   */
  private function updateSchedule(
    InterventionRecurrenceRecord $record,
    ?string $timezone,
    ?int $leadTimeDays,
    ?DateTimeImmutable $nextOccurrenceAt,
    bool $hasTimezone,
    bool $hasLeadTimeDays,
    bool $hasNextOccurrenceAt,
  ): void {
    if ($hasTimezone && null !== $timezone) {
      $record->timezone = $timezone;
    }
    if ($hasLeadTimeDays && null !== $leadTimeDays) {
      $record->leadTimeDays = $leadTimeDays;
    }
    if ($hasNextOccurrenceAt && null !== $nextOccurrenceAt) {
      $record->nextOccurrenceAt = $nextOccurrenceAt;
    }
  }

  /**
   * Method updateLifecycle
   *
   * Applies present end-date and active-state fields from a merge-patch request.
   *
   * @access private
   *
   * @param InterventionRecurrenceRecord $record recurrence record being updated
   * @param DateTimeImmutable|null $endAt replacement end date when present
   * @param bool|null $isActive replacement active state when present
   * @param bool $hasEndAt whether the request supplied the end-date field
   * @param bool $hasIsActive whether the request supplied the active-state field
   *
   * @return void
   */
  private function updateLifecycle(
    InterventionRecurrenceRecord $record,
    ?DateTimeImmutable $endAt,
    ?bool $isActive,
    bool $hasEndAt,
    bool $hasIsActive,
  ): void {
    if ($hasEndAt) {
      $record->endAt = $endAt;
    }
    if ($hasIsActive && null !== $isActive) {
      $record->isActive = $isActive;
    }
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
