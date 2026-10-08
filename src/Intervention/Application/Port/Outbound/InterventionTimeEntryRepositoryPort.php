<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

/**
 * InterventionTimeEntryRepositoryPort.
 *
 * @category Intervention
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionTimeEntryRepositoryPort
{
  /**
   * Reads task contribution rights, optionally locking the context for a write.
   *
   * @since 1.0.0
   *
   * @param string $taskId intervention work-item identifier
   * @param bool $lock whether the caller requires the task context to be locked for a write
   *
   * @return ?\Intervention\Application\Contract\Time\TimeEntryTaskContext task contribution context, or null when the task does not exist
   */
  public function context(string $taskId, bool $lock = false): ?\Intervention\Application\Contract\Time\TimeEntryTaskContext;

  /**
   * Finds a current time entry without loading its complete retained history.
   *
   * @since 1.0.0
   *
   * @param string $id stable resource identifier, retained across idempotent retries
   *
   * @return ?\Intervention\Application\Contract\Time\TimeEntryView current entry with one latest version and an older-history cursor, null when absent
   */
  public function find(string $id): ?\Intervention\Application\Contract\Time\TimeEntryView;

  /**
   * Lists the task journal within the requested beneficiary scope.
   *
   * @since 1.0.0
   *
   * @param string $taskId intervention work-item identifier
   * @param ?string $memberId beneficiary filter; null includes all authorized contributions on the task
   * @param int $page one-based journal page
   * @param int $itemsPerPage maximum returned entries, from 1 to 100
   *
   * @return list<\Intervention\Application\Contract\Time\TimeEntryView>
   */
  public function list(string $taskId, ?string $memberId, int $page = 1, int $itemsPerPage = 30): array;

  /**
   * Method count
   *
   * Counts the complete authorized journal without hydrating its entries.
   *
   * @access public
   *
   * @param string $taskId owning task identifier
   * @param ?string $memberId beneficiary filter, null for the management scope
   *
   * @return int exact number of scoped entries, including cancellations
   */
  public function count(string $taskId, ?string $memberId): int;

  /**
   * Method versions
   *
   * Reads retained revisions newest first with a stable exclusive revision cursor.
   *
   * @access public
   *
   * @param string $entryId authorized entry identifier
   * @param ?int $beforeRevision exclusive upper revision bound
   * @param int $limit bounded window including at most one continuation probe
   *
   * @return list<\Intervention\Application\Contract\Time\TimeEntryVersionView> retained revision window
   */
  public function versions(string $entryId, ?int $beforeRevision, int $limit): array;

  /**
   * Method countVersions
   *
   * Counts retained history without loading its contents.
   *
   * @access public
   *
   * @param string $entryId authorized entry identifier
   *
   * @return int exact retained revision count
   */
  public function countVersions(string $entryId): int;

  /**
   * Method originalVersion
   *
   * Reads only the original revision needed for stable create-request replay.
   *
   * @access public
   *
   * @param string $entryId entry identifier
   *
   * @return ?\Intervention\Application\Contract\Time\TimeEntryVersionView original revision, null if unavailable
   */
  public function originalVersion(string $entryId): ?\Intervention\Application\Contract\Time\TimeEntryVersionView;

  /**
   * Persists the independent entry and its audited version without changing the intervention revision.
   *
   * @since 1.0.0
   *
   * @param \Intervention\Domain\Model\TimeEntry\TimeEntry $entry independent time-entry aggregate or view
   * @param string $organizationId organization identifier that scopes this operation
   * @param string $actorId member who authored the operation, not necessarily its beneficiary
   *
   * @return \Intervention\Application\Contract\Time\TimeEntryView persisted entry and retained revision history
   */
  public function save(\Intervention\Domain\Model\TimeEntry\TimeEntry $entry, string $organizationId, string $actorId): \Intervention\Application\Contract\Time\TimeEntryView;
}
