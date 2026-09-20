<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

/**
 * InterventionTimeEntryRepositoryPort.
 *
 * @category Intervention
 *
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
   * Finds a time entry together with its retained versions.
   *
   * @since 1.0.0
   *
   * @param string $id stable resource identifier, retained across idempotent retries
   *
   * @return ?\Intervention\Application\Contract\Time\TimeEntryView Persisted entry and retained revision history. Returns null when the entry does not exist.
   */
  public function find(string $id): ?\Intervention\Application\Contract\Time\TimeEntryView;

  /**
   * Lists the task journal within the requested beneficiary scope.
   *
   * @since 1.0.0
   *
   * @param string $taskId intervention work-item identifier
   * @param ?string $memberId beneficiary filter; null includes all authorized contributions on the task
   *
   * @return list<\Intervention\Application\Contract\Time\TimeEntryView>
   */
  public function list(string $taskId, ?string $memberId): array;

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
