<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Sweep\EscalateNonConformitySlaBreaches;

use DateTimeImmutable;
use Inspection\Application\Contract\Sla\{NonConformitySlaCandidate, NonConformitySlaPolicy};
use Inspection\Application\Port\Outbound\Compliance\NonConformitySlaPolicyPort;
use Inspection\Application\Port\Outbound\NonConformitySlaPort;
use Inspection\Application\Service\NonConformitySlaNotifier;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\ClockPort;

use function count;
use function sprintf;

/**
 * UseCase EscalateNonConformitySlaBreachesHandler.
 *
 * Idempotent, safe-to-re-run SLA escalation sweep: pages through every
 * unresolved non-conformity not yet signalled
 * ({@see NonConformitySlaPort}), resolves the owning organization's
 * per-severity resolution SLA through
 * {@see NonConformitySlaPolicyPort} (cached per organization for the run),
 * and escalates each breach — a non-conformity older than its SLA — to the
 * organization's administrators through {@see NonConformitySlaNotifier}.
 *
 * Anti-duplicate: a candidate is only selected while its
 * `sla_breach_notified_at` stamp is `null`, and the stamp is set immediately
 * after the escalation is sent — so a repeat tick never re-announces the
 * same breach while the non-conformity stays unresolved. Resolving the
 * non-conformity removes it from the sweep entirely; reopening one that was
 * resolved clears the stamp at the source
 * (`DoctrineNonConformityRepository::save()`), so a still-breached reopened
 * non-conformity is deliberately re-escalated — mirroring how an
 * intervention reschedule re-arms its due-date reminders.
 *
 * Every page is processed independently to keep memory bounded regardless of
 * how many organizations/non-conformities exist. A candidate whose severity
 * has no SLA (unknown severity) is skipped and left unstamped.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EscalateNonConformitySlaBreachesHandler implements CommandHandler
{
  // #region Constants
  /**
   * Page size used for the sweep, keeping every batch bounded in memory.
   */
  private const int PAGE_SIZE = 200;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NonConformitySlaPort $candidates the escalation candidates port
   * @param NonConformitySlaPolicyPort $slaPolicy the cross-module organization SLA policy port
   * @param NonConformitySlaNotifier $notifier the escalation notifier
   * @param ClockPort $clock the clock port
   */
  public function __construct(
    private NonConformitySlaPort $candidates,
    private NonConformitySlaPolicyPort $slaPolicy,
    private NonConformitySlaNotifier $notifier,
    private ClockPort $clock,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param EscalateNonConformitySlaBreachesCommand $command the command value
   *
   * @return EscalateNonConformitySlaBreachesResult the command result
   */
  public function __invoke(EscalateNonConformitySlaBreachesCommand $command): EscalateNonConformitySlaBreachesResult
  {
    $now = $this->clock->now();
    $policies = [];
    $escalated = 0;
    $offset = 0;

    do {
      $page = $this->candidates->pageOpenUnnotified(self::PAGE_SIZE, $offset);

      foreach ($page->items as $candidate) {
        $policy = $policies[$candidate->organizationId] ??= $this->slaPolicy->slaPolicy($candidate->organizationId);

        $slaDays = $this->breachedSlaDays($candidate, $policy, $now);
        if (null === $slaDays) {
          continue;
        }

        $this->notifier->escalate(
          $candidate->organizationId,
          $candidate->inspectionId,
          $candidate->id,
          $candidate->severity,
          $slaDays,
          $candidate->createdAt,
        );
        $this->candidates->markSlaBreachNotified($candidate->id, $now);
        ++$escalated;
      }

      $offset += self::PAGE_SIZE;
    } while (self::PAGE_SIZE === count($page->items));

    return new EscalateNonConformitySlaBreachesResult(escalatedCount: $escalated);
  }

  /**
   * Method breachedSlaDays.
   *
   * A candidate breaches its SLA when its age exceeds the effective SLA for
   * its severity: `createdAt + slaDays < now`. A severity with no SLA never
   * breaches.
   *
   * @since 1.0.0
   *
   * @param NonConformitySlaCandidate $candidate the candidate value
   * @param NonConformitySlaPolicy $policy the owning organization's SLA policy
   * @param DateTimeImmutable $now the current instant
   *
   * @return ?int the breached SLA in days, or null when the candidate is within its SLA
   */
  private function breachedSlaDays(NonConformitySlaCandidate $candidate, NonConformitySlaPolicy $policy, DateTimeImmutable $now): ?int
  {
    $slaDays = $policy->slaDaysFor($candidate->severity);
    if (null === $slaDays) {
      return null;
    }

    return $candidate->createdAt->modify(sprintf('+%d days', $slaDays)) < $now ? $slaDays : null;
  }
  // #endregion
}
