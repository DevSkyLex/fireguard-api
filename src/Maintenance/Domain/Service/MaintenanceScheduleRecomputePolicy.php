<?php

declare(strict_types=1);

namespace Maintenance\Domain\Service;

use DateInterval;
use DateTimeImmutable;
use Maintenance\Domain\ValueObject\{MaintenanceDueStatus, PeriodicityInterval};

/**
 * Service MaintenanceScheduleRecomputePolicy.
 *
 * Pure domain policy computing a maintenance schedule's effective interval,
 * next due date and due status. Framework- and I/O-free by design so every
 * transition is unit-testable without a database or a clock adapter beyond
 * the reference instant passed in by the caller.
 *
 * Rules:
 *
 * - Effective interval resolution: a per-schedule override always wins over
 *   the organization's compliance periodicity for the equipment type; when
 *   neither is set the equipment type is untracked.
 * - Due status: `unscheduled` when there is no effective interval;
 *   `overdue` when there is an effective interval but the equipment has
 *   never been inspected (no next due date to compare against); otherwise
 *   `overdue` once now is strictly after the due date, `due_soon` inside
 *   the organization's reminder window, `up_to_date` otherwise.
 * - Reminder re-arming: the anti-duplicate `reminded_for` marker must be
 *   reset whenever the next due date changes so a schedule that moved
 *   (e.g. after a fresh inspection) can be reminded again.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenanceScheduleRecomputePolicy
{
  // #region Methods
  /**
   * Method resolveEffectiveInterval.
   *
   * Resolves the effective periodicity: a per-schedule override wins over
   * the organization's compliance periodicity for the equipment type; null
   * when neither applies (untracked equipment type).
   *
   * @since 1.0.0
   *
   * @param ?string $intervalOverride the per-schedule ISO-8601 override, if any
   * @param ?string $organizationPeriodicity the organization compliance periodicity for the equipment type, if any
   *
   * @return ?PeriodicityInterval the effective interval, or null when untracked
   */
  public function resolveEffectiveInterval(?string $intervalOverride, ?string $organizationPeriodicity): ?PeriodicityInterval
  {
    $raw = $intervalOverride ?? $organizationPeriodicity;

    return null === $raw ? null : PeriodicityInterval::fromString($raw);
  }

  /**
   * Method computeNextDueAt.
   *
   * Computes the next due date from the last inspection closure and the
   * effective interval. Null when the equipment is untracked or has never
   * been inspected.
   *
   * @since 1.0.0
   *
   * @param ?DateTimeImmutable $lastInspectionClosedAt the last inspection closure date, if any
   * @param ?PeriodicityInterval $effectiveInterval the effective periodicity, if any
   *
   * @return ?DateTimeImmutable the next due date, or null
   */
  public function computeNextDueAt(?DateTimeImmutable $lastInspectionClosedAt, ?PeriodicityInterval $effectiveInterval): ?DateTimeImmutable
  {
    if (null === $effectiveInterval || null === $lastInspectionClosedAt) {
      return null;
    }

    return $effectiveInterval->addTo($lastInspectionClosedAt);
  }

  /**
   * Method computeDueStatus.
   *
   * @since 1.0.0
   *
   * @param ?DateTimeImmutable $nextDueAt the next due date, if any
   * @param ?PeriodicityInterval $effectiveInterval the effective periodicity, if any
   * @param DateTimeImmutable $now the reference instant
   * @param int $reminderWindowDays the organization's reminder window, in days
   *
   * @return MaintenanceDueStatus the computed due status
   */
  public function computeDueStatus(
    ?DateTimeImmutable $nextDueAt,
    ?PeriodicityInterval $effectiveInterval,
    DateTimeImmutable $now,
    int $reminderWindowDays,
  ): MaintenanceDueStatus {
    if (null === $effectiveInterval) {
      return MaintenanceDueStatus::UNSCHEDULED;
    }

    if (null === $nextDueAt) {
      // A periodicity applies but the equipment has never been inspected:
      // treated as immediately due.
      return MaintenanceDueStatus::OVERDUE;
    }

    if ($now > $nextDueAt) {
      return MaintenanceDueStatus::OVERDUE;
    }

    $reminderThreshold = $nextDueAt->sub(new DateInterval('P' . $reminderWindowDays . 'D'));
    if ($now >= $reminderThreshold) {
      return MaintenanceDueStatus::DUE_SOON;
    }

    return MaintenanceDueStatus::UP_TO_DATE;
  }

  /**
   * Method shouldResetRemindedFor.
   *
   * Determines whether the anti-duplicate reminder marker must be reset:
   * true whenever the next due date changed (including transitions to/from
   * null), so a schedule that moved can be reminded again.
   *
   * @since 1.0.0
   *
   * @param ?DateTimeImmutable $previousNextDueAt the previously stored next due date
   * @param ?DateTimeImmutable $newNextDueAt the newly computed next due date
   *
   * @return bool true when the reminder marker must be reset
   */
  public function shouldResetRemindedFor(?DateTimeImmutable $previousNextDueAt, ?DateTimeImmutable $newNextDueAt): bool
  {
    return $previousNextDueAt?->getTimestamp() !== $newNextDueAt?->getTimestamp();
  }
  // #endregion
}
