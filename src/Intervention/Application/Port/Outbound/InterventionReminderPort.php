<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use DateTimeImmutable;
use Intervention\Application\Contract\Reminder\InterventionReminderPage;

/**
 * Interface InterventionReminderPort.
 *
 * Selects interventions whose `dueAt` is approaching or has passed, and
 * records the anti-spam stamps that keep the reminder sweep from
 * re-announcing an already-notified threshold.
 *
 * Candidates are restricted to statuses where field work is still expected
 * (`planned`, `in_progress`, `changes_requested`) — a draft is not yet
 * scheduled, and `submitted`/`published`/`abandoned` no longer need action.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionReminderPort
{
  // #region Methods
  /**
   * Method pageDueSoon.
   *
   * Pages through candidates whose `dueAt` falls between `$now` and
   * `$threshold` (inclusive) and which have not yet been notified for the
   * current `dueAt` (`due_soon_notified_at IS NULL`).
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current instant
   * @param DateTimeImmutable $threshold the upper bound of the due-soon window
   * @param int $limit the maximum number of results
   * @param int $offset the result offset
   *
   * @return InterventionReminderPage the due-soon candidate page
   */
  public function pageDueSoon(DateTimeImmutable $now, DateTimeImmutable $threshold, int $limit, int $offset): InterventionReminderPage;

  /**
   * Method pageOverdue.
   *
   * Pages through candidates whose `dueAt` is strictly before `$now` and
   * which have not yet been notified for the current `dueAt`
   * (`overdue_notified_at IS NULL`).
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current instant
   * @param int $limit the maximum number of results
   * @param int $offset the result offset
   *
   * @return InterventionReminderPage the overdue candidate page
   */
  public function pageOverdue(DateTimeImmutable $now, int $limit, int $offset): InterventionReminderPage;

  /**
   * Method markDueSoonNotified.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param DateTimeImmutable $at the instant the reminder was sent
   */
  public function markDueSoonNotified(string $interventionId, DateTimeImmutable $at): void;

  /**
   * Method markOverdueNotified.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param DateTimeImmutable $at the instant the reminder was sent
   */
  public function markOverdueNotified(string $interventionId, DateTimeImmutable $at): void;
  // #endregion
}
