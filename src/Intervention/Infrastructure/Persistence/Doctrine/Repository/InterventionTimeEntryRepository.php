<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Time\{TimeEntryTaskContext, TimeEntryVersionView, TimeEntryView};
use Intervention\Application\Port\Outbound\InterventionTimeEntryRepositoryPort;
use Intervention\Domain\Model\TimeEntry\TimeEntry;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionTimeEntryRecord, InterventionTimeEntryVersionRecord, InterventionWorkItemAssignmentRecord, InterventionWorkItemRecord};
use LogicException;

use function array_map;

/**
 * InterventionTimeEntryRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionTimeEntryRepository implements InterventionTimeEntryRepositoryPort
{
  /**
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager explicitly configured main entity manager
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * Reads task contribution rights, optionally locking the context for a write.
   *
   * @since 1.0.0
   *
   * @param string $taskId intervention work-item identifier
   * @param bool $lock whether the caller requires the task context to be locked for a write
   *
   * @return ?TimeEntryTaskContext task contribution context, or null when the task does not exist
   */
  public function context(string $taskId, bool $lock = false): ?TimeEntryTaskContext
  {
    $task = $this->entityManager->find(InterventionWorkItemRecord::class, $taskId);
    if (!$task instanceof InterventionWorkItemRecord || null === $task->intervention || null === $task->intervention->organization) {
      return null;
    }
    $parent = $task->intervention;
    if ($lock) {
      $this->entityManager->refresh($parent, LockMode::PESSIMISTIC_WRITE);
      $this->entityManager->refresh($task);
    }
    $history = $this->entityManager->getRepository(InterventionWorkItemAssignmentRecord::class)->findBy(['workItem' => $task]);
    $organization = $parent->organization;
    if (null === $organization) {
      return null;
    }

    return new TimeEntryTaskContext(
      $taskId,
      $parent->id,
      $organization->id,
      $task->assigneeId,
      $parent->responsibleId,
      $parent->participants,
      array_map(static fn (InterventionWorkItemAssignmentRecord $a): string => $a->memberId, $history),
    );
  }

  /**
   * Finds a time entry together with its retained versions.
   *
   * @since 1.0.0
   *
   * @param string $id stable resource identifier, retained across idempotent retries
   *
   * @return ?TimeEntryView Persisted entry and retained revision history. Returns null when the entry does not exist.
   */
  public function find(string $id): ?TimeEntryView
  {
    $record = $this->entityManager->find(InterventionTimeEntryRecord::class, $id);
    if (!$record instanceof InterventionTimeEntryRecord) {
      return null;
    }
    $this->entityManager->refresh($record);

    return $this->view($record);
  }

  /**
   * Lists the task journal within the requested beneficiary scope.
   *
   * @since 1.0.0
   *
   * @param string $taskId intervention work-item identifier
   * @param ?string $memberId beneficiary filter; null includes all authorized contributions on the task
   *
   * @return list<TimeEntryView>
   */
  public function list(string $taskId, ?string $memberId): array
  {
    $criteria = ['workItem' => $taskId];
    if (null !== $memberId) {
      $criteria['memberId'] = $memberId;
    }

    return array_map($this->view(...), $this->entityManager->getRepository(InterventionTimeEntryRecord::class)->findBy($criteria, ['workedOn' => 'DESC', 'id' => 'ASC']));
  }

  /**
   * Persists the independent entry and its audited version without changing the intervention revision.
   *
   * @since 1.0.0
   *
   * @param TimeEntry $entry independent time-entry aggregate or view
   * @param string $organizationId organization identifier that scopes this operation
   * @param string $actorId member who authored the operation, not necessarily its beneficiary
   *
   * @return TimeEntryView persisted entry and retained revision history
   */
  public function save(TimeEntry $entry, string $organizationId, string $actorId): TimeEntryView
  {
    $record = $this->entityManager->find(InterventionTimeEntryRecord::class, $entry->id);
    $now = new DateTimeImmutable();
    if (!$record instanceof InterventionTimeEntryRecord) {
      $record = new InterventionTimeEntryRecord();
      $record->id = $entry->id;
      $record->workItem = $this->entityManager->getReference(InterventionWorkItemRecord::class, $entry->workItemId);
      $record->organizationId = $organizationId;
      $record->memberId = $entry->memberId;
      $record->createdBy = $actorId;
      $record->createdAt = $now;
      $this->entityManager->persist($record);
    }
    $record->workedOn = $entry->workedOn;
    $record->minutes = $entry->minutes;
    $record->note = $entry->note;
    $record->revision = $entry->revision;
    $record->cancelled = $entry->cancelled;
    $record->updatedBy = $actorId;
    $record->updatedAt = $now;
    $version = new InterventionTimeEntryVersionRecord();
    $version->entry = $record;
    $version->revision = $entry->revision;
    $version->workedOn = $entry->workedOn;
    $version->minutes = $entry->minutes;
    $version->note = $entry->note;
    $version->cancelled = $entry->cancelled;
    $version->actorId = $actorId;
    $version->recordedAt = $now;
    $this->entityManager->persist($version);
    $this->entityManager->flush();

    return $this->view($record);
  }

  /**
   * Maps a persisted time entry and its versions into the published journal contract.
   *
   * @since 1.0.0
   *
   * @param InterventionTimeEntryRecord $record persisted record being mapped or updated
   *
   * @return TimeEntryView persisted entry and retained revision history
   */
  private function view(InterventionTimeEntryRecord $record): TimeEntryView
  {
    $versions = $this->entityManager->getRepository(InterventionTimeEntryVersionRecord::class)->findBy(['entry' => $record], ['revision' => 'ASC']);
    $task = $record->workItem ?? throw new LogicException('Time entry task is missing.');

    return new TimeEntryView(
      $record->id,
      $task->id,
      $record->memberId,
      $record->workedOn,
      $record->minutes,
      $record->note,
      $record->revision,
      $record->cancelled,
      $record->createdBy,
      $record->updatedBy,
      $record->createdAt->format('c'),
      $record->updatedAt->format('c'),
      array_map(static fn (InterventionTimeEntryVersionRecord $v): TimeEntryVersionView => new TimeEntryVersionView($v->revision, $v->workedOn, $v->minutes, $v->note, $v->cancelled, $v->actorId, $v->recordedAt->format('c')), $versions),
    );
  }
}
