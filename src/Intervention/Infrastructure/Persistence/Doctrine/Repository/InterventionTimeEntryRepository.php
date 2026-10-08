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
use InvalidArgumentException;
use LogicException;

use function array_map;
use function intdiv;

use const PHP_INT_MAX;

/**
 * InterventionTimeEntryRepository.
 *
 * @category Repository
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
   * Finds a current entry with one latest version and a cursor to its older history.
   *
   * @since 1.0.0
   *
   * @param string $id stable resource identifier, retained across idempotent retries
   *
   * @return ?TimeEntryView current entry without a retained-history query, null when absent
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
   * @param int $page one-based journal page
   * @param int $itemsPerPage maximum hydrated entries, from 1 to 100
   *
   * @return list<TimeEntryView>
   */
  public function list(string $taskId, ?string $memberId, int $page = 1, int $itemsPerPage = 30): array
  {
    if ($page < 1 || $itemsPerPage < 1 || $itemsPerPage > 100 || $page > intdiv(PHP_INT_MAX, $itemsPerPage)) {
      throw new InvalidArgumentException('Invalid time journal pagination.');
    }
    $criteria = ['workItem' => $taskId];
    if (null !== $memberId) {
      $criteria['memberId'] = $memberId;
    }

    return array_map($this->view(...), $this->entityManager->getRepository(InterventionTimeEntryRecord::class)->findBy($criteria, ['workedOn' => 'DESC', 'id' => 'ASC'], $itemsPerPage, ($page - 1) * $itemsPerPage));
  }

  /**
   * Method count
   *
   * Counts all entries in the selected beneficiary scope without hydration.
   *
   * @access public
   *
   * @param string $taskId owning task
   * @param ?string $memberId beneficiary filter, null for all authorized members
   *
   * @return int complete scoped entry count
   */
  public function count(string $taskId, ?string $memberId): int
  {
    $criteria = ['workItem' => $taskId];
    if (null !== $memberId) {
      $criteria['memberId'] = $memberId;
    }

    return $this->entityManager->getRepository(InterventionTimeEntryRecord::class)->count($criteria);
  }

  /**
   * Method versions
   *
   * Uses the entry/revision primary key for a bounded descending history window.
   *
   * @access public
   *
   * @param string $entryId authorized entry
   * @param ?int $beforeRevision exclusive revision cursor
   * @param int $limit at most 101 rows including a continuation probe
   *
   * @return list<TimeEntryVersionView> retained history window
   */
  public function versions(string $entryId, ?int $beforeRevision, int $limit): array
  {
    if ($limit < 1 || $limit > 101 || (null !== $beforeRevision && $beforeRevision < 1)) {
      throw new InvalidArgumentException('Invalid time history pagination.');
    }
    $query = $this->entityManager->createQueryBuilder()
      ->select('version')
      ->from(InterventionTimeEntryVersionRecord::class, 'version')
      ->where('IDENTITY(version.entry) = :entry')
      ->setParameter('entry', $entryId)
      ->orderBy('version.revision', 'DESC')
      ->setMaxResults($limit);
    if (null !== $beforeRevision) {
      $query->andWhere('version.revision < :before')->setParameter('before', $beforeRevision);
    }
    /** @var list<InterventionTimeEntryVersionRecord> $versions */
    $versions = $query->getQuery()->getResult();

    return array_map($this->versionView(...), $versions);
  }

  /**
   * Method countVersions
   *
   * Returns a scalar count of all retained revisions.
   *
   * @access public
   *
   * @param string $entryId authorized entry
   *
   * @return int exact durable version count
   */
  public function countVersions(string $entryId): int
  {
    return $this->entityManager->getRepository(InterventionTimeEntryVersionRecord::class)->count(['entry' => $entryId]);
  }

  /**
   * Method originalVersion
   *
   * Fetches one primary-key row for create idempotency regardless of journal age.
   *
   * @access public
   *
   * @param string $entryId stable entry identifier
   *
   * @return ?TimeEntryVersionView original persisted request
   */
  public function originalVersion(string $entryId): ?TimeEntryVersionView
  {
    $version = $this->entityManager->find(InterventionTimeEntryVersionRecord::class, ['entry' => $entryId, 'revision' => 1]);

    return $version instanceof InterventionTimeEntryVersionRecord ? $this->versionView($version) : null;
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
   * Maps current scalar state without loading its durable history.
   *
   * @since 1.0.0
   *
   * @param InterventionTimeEntryRecord $record persisted record being mapped or updated
   *
   * @return TimeEntryView persisted entry and retained revision history
   */
  private function view(InterventionTimeEntryRecord $record): TimeEntryView
  {
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
      [new TimeEntryVersionView($record->revision, $record->workedOn, $record->minutes, $record->note, $record->cancelled, $record->updatedBy, $record->updatedAt->format('c'))],
      $record->revision,
      $record->revision > 1 ? $record->revision : null,
    );
  }

  /**
   * Method versionView
   *
   * Maps one persisted immutable revision without loading its owning entry.
   *
   * @access private
   *
   * @param InterventionTimeEntryVersionRecord $version retained revision row
   *
   * @return TimeEntryVersionView transport-independent history view
   */
  private function versionView(InterventionTimeEntryVersionRecord $version): TimeEntryVersionView
  {
    return new TimeEntryVersionView($version->revision, $version->workedOn, $version->minutes, $version->note, $version->cancelled, $version->actorId, $version->recordedAt->format('c'));
  }
}
