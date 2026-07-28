<?php

declare(strict_types=1);

namespace Compliance\Application\Service;

use Compliance\Domain\ValueObject\ComplianceStatus;

use function array_map;
use function max;

/**
 * Service ComplianceStatusPolicy.
 *
 * Pure, I/O-free grading rule turning raw driver counts into a transparent
 * `ComplianceStatus` verdict:
 *
 * - `NON_COMPLIANT` when any equipment is overdue OR any critical
 *   non-conformity is open — a hard regulatory breach.
 * - `AT_RISK` when equipment is due soon OR a high-severity non-conformity
 *   is open, with no hard breach — a warning signal.
 * - `COMPLIANT` when tracked equipment exists and neither of the above
 *   applies.
 * - `NOT_APPLICABLE` when there is no schedule-tracked equipment and no
 *   open high/critical non-conformity to grade against (nothing to assess).
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ComplianceStatusPolicy
{
  // #region Methods
  /**
   * Method grade.
   *
   * @since 1.0.0
   *
   * @param int $overdueEquipmentCount overdue equipment count
   * @param int $dueSoonEquipmentCount due-soon equipment count
   * @param int $trackedEquipmentCount equipment actually subject to a periodicity (up-to-date + due-soon + overdue)
   * @param int $openHighNonConformityCount open high-severity non-conformity count
   * @param int $openCriticalNonConformityCount open critical-severity non-conformity count
   *
   * @return ComplianceStatus the graded verdict
   */
  public static function grade(
    int $overdueEquipmentCount,
    int $dueSoonEquipmentCount,
    int $trackedEquipmentCount,
    int $openHighNonConformityCount,
    int $openCriticalNonConformityCount,
  ): ComplianceStatus {
    if ($overdueEquipmentCount > 0 || $openCriticalNonConformityCount > 0) {
      return ComplianceStatus::NON_COMPLIANT;
    }

    if ($dueSoonEquipmentCount > 0 || $openHighNonConformityCount > 0) {
      return ComplianceStatus::AT_RISK;
    }

    if ($trackedEquipmentCount > 0) {
      return ComplianceStatus::COMPLIANT;
    }

    return ComplianceStatus::NOT_APPLICABLE;
  }

  /**
   * Method worstOf.
   *
   * Rolls up a list of facility statuses into a single organization-level
   * verdict: the worst non-`NOT_APPLICABLE` status among them, or
   * `NOT_APPLICABLE` when every facility is `NOT_APPLICABLE` (or the list is
   * empty — an organization with no facilities has nothing to assess).
   *
   * @since 1.0.0
   *
   * @param list<ComplianceStatus> $statuses the facility statuses to roll up
   *
   * @return ComplianceStatus the organization rollup verdict
   */
  public static function worstOf(array $statuses): ComplianceStatus
  {
    $ranks = array_map(static fn (ComplianceStatus $status): int => $status->severityRank(), $statuses);

    if ([] === $ranks) {
      return ComplianceStatus::NOT_APPLICABLE;
    }

    $worstRank = max($ranks);

    foreach (ComplianceStatus::cases() as $candidate) {
      if ($candidate->severityRank() === $worstRank) {
        return $candidate;
      }
    }

    // @codeCoverageIgnoreStart
    // Unreachable: $worstRank is max() over the ranks of the very cases being
    // iterated here, and every ComplianceStatus has a distinct severityRank(),
    // so the loop always returns. Kept so the method is total for the analyser.
    return ComplianceStatus::NOT_APPLICABLE;
    // @codeCoverageIgnoreEnd
  }
  // #endregion
}
