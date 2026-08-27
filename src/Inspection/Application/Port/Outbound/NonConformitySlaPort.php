<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use DateTimeImmutable;
use Inspection\Application\Contract\Sla\NonConformitySlaPage;

/**
 * Port NonConformitySlaPort.
 *
 * Selects the unresolved non-conformities the SLA escalation sweep must
 * examine, and records the anti-duplicate stamp that keeps the sweep from
 * re-announcing an already-signalled breach — mirrors
 * `Intervention\Application\Port\Outbound\InterventionReminderPort`.
 *
 * Candidates are restricted to unresolved statuses (`open`, `in_progress`):
 * a `done` or `waived` non-conformity no longer needs escalation. The SLA
 * itself is per-organization and per-severity, so the breach computation
 * happens in the handler — the port only guarantees "unresolved and not yet
 * signalled".
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface NonConformitySlaPort
{
  // #region Methods
  /**
   * Method pageOpenUnnotified.
   *
   * Pages through unresolved non-conformities that have not yet been
   * signalled as breaching their SLA (`sla_breach_notified_at IS NULL`),
   * ordered by identifier for a stable sweep.
   *
   * @since 1.0.0
   *
   * @param int $limit the maximum number of results
   * @param int $offset the result offset
   *
   * @return NonConformitySlaPage the candidate page
   */
  public function pageOpenUnnotified(int $limit, int $offset): NonConformitySlaPage;

  /**
   * Method markSlaBreachNotified.
   *
   * @since 1.0.0
   *
   * @param string $nonConformityId the non-conformity identifier
   * @param DateTimeImmutable $at the instant the escalation was sent
   */
  public function markSlaBreachNotified(string $nonConformityId, DateTimeImmutable $at): void;
  // #endregion
}
