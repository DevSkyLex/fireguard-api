<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Sweep\SendWeeklyDigests;

use Organization\Application\Port\Inbound\OrganizationNotificationPolicyPort;
use Organization\Application\Port\Outbound\{InterventionStatisticsPort, MaintenanceStatisticsPort, NonConformityStatisticsPort, OrganizationRepositoryPort};
use Organization\Application\Service\{OrganizationWeeklyDigest, OrganizationWeeklyDigestNotifier};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, LoggerPort};
use Throwable;

use function count;

/**
 * UseCase SendWeeklyDigestsHandler.
 *
 * Weekly recap sweep: pages through every ACTIVE organization and sends its
 * administrators one digest email aggregating, per organization, the overdue
 * field interventions, the maintenance deadlines (due within the next seven
 * days plus the already-overdue ones), and the unresolved non-conformities
 * (including those past their resolution SLA).
 *
 * The aggregation reads only cross-module statistics ports the Organization
 * module already owns for its dashboard. Two gates apply before any data is
 * fetched: the organization's `weeklyDigest` category toggle and its
 * `emailEnabled` channel toggle — the digest is email-only by design. A
 * digest whose counters are all zero is deliberately NOT sent: silence
 * means nothing needs attention.
 *
 * Idempotence: the sweep carries no per-run stamp — the schedule itself is
 * stateful and lock-guarded (`OrganizationScheduleProvider`), so the weekly
 * tick fires once; re-running the command manually simply re-sends the
 * current snapshot, which is safe. Best-effort per organization: one failing
 * organization must never starve the rest of the sweep.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SendWeeklyDigestsHandler implements CommandHandler
{
  // #region Constants
  /**
   * Page size used for the organization sweep, keeping every batch bounded
   * in memory.
   */
  private const int PAGE_SIZE = 100;

  /**
   * Maximum number of detail lines fetched per digest section.
   */
  private const int DETAIL_LIMIT = 5;

  /**
   * The "due soon" maintenance window looked at by the digest.
   */
  private const string DUE_SOON_WINDOW = '+7 days';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizations the organization repository port
   * @param OrganizationNotificationPolicyPort $policy the organization notification policy port
   * @param InterventionStatisticsPort $interventionStatistics the cross-module intervention statistics port
   * @param MaintenanceStatisticsPort $maintenanceStatistics the cross-module maintenance statistics port
   * @param NonConformityStatisticsPort $nonConformityStatistics the cross-module non-conformity statistics port
   * @param OrganizationWeeklyDigestNotifier $notifier the digest notifier
   * @param ClockPort $clock the clock port
   * @param LoggerPort $logger the logger port
   */
  public function __construct(
    private OrganizationRepositoryPort $organizations,
    private OrganizationNotificationPolicyPort $policy,
    private InterventionStatisticsPort $interventionStatistics,
    private MaintenanceStatisticsPort $maintenanceStatistics,
    private NonConformityStatisticsPort $nonConformityStatistics,
    private OrganizationWeeklyDigestNotifier $notifier,
    private ClockPort $clock,
    private LoggerPort $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param SendWeeklyDigestsCommand $command the command value
   *
   * @return SendWeeklyDigestsResult the command result
   */
  public function __invoke(SendWeeklyDigestsCommand $command): SendWeeklyDigestsResult
  {
    $scanned = 0;
    $sent = 0;
    $offset = 0;

    do {
      $page = $this->organizations->pageActiveIds(self::PAGE_SIZE, $offset);

      foreach ($page as $organizationId) {
        ++$scanned;

        try {
          $sent += $this->processOrganization($organizationId);
        } catch (Throwable $exception) {
          $this->logger->warning('Weekly digest sweep failed for one organization.', [
            'organizationId' => $organizationId,
            'error' => $exception->getMessage(),
          ]);
        }
      }

      $offset += self::PAGE_SIZE;
    } while (self::PAGE_SIZE === count($page));

    return new SendWeeklyDigestsResult(organizationsScanned: $scanned, digestsSent: $sent);
  }

  /**
   * Method processOrganization.
   *
   * Applies the organization's toggles, aggregates its digest, and sends it
   * when it carries anything to report.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return int the number of digest emails sent for this organization
   */
  private function processOrganization(string $organizationId): int
  {
    $policy = $this->policy->notificationPolicy($organizationId);
    if (!$policy->weeklyDigest || !$policy->emailEnabled) {
      return 0;
    }

    $digest = $this->buildDigest($organizationId);
    if ($digest->isEmpty()) {
      return 0;
    }

    $organization = $this->organizations->findById(OrganizationId::fromString($organizationId));
    if (!$organization instanceof Organization) {
      return 0;
    }

    return $this->notifier->notify($organizationId, (string) $organization->name(), $digest);
  }

  /**
   * Method buildDigest.
   *
   * Aggregates one organization's digest counters through the existing
   * cross-module statistics ports, fetching the bounded detail lines only
   * for the sections that have something to show.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return OrganizationWeeklyDigest the digest snapshot
   */
  private function buildDigest(string $organizationId): OrganizationWeeklyDigest
  {
    $now = $this->clock->now();
    $windowEnd = $now->modify(self::DUE_SOON_WINDOW);

    $overdueInterventionsCount = $this->interventionStatistics->countOverview($organizationId, $now)['overdue'];

    $maintenanceOverview = $this->maintenanceStatistics->countDueOverview($organizationId, $now, $windowEnd);
    $maintenanceDueSoonCount = $maintenanceOverview['due_soon'];
    $maintenanceOverdueCount = $maintenanceOverview['overdue'];

    $nonConformityCounts = $this->nonConformityStatistics->countNonConformitiesByStatus($organizationId);
    $openNonConformitiesCount = (int) ($nonConformityCounts['open'] ?? 0) + (int) ($nonConformityCounts['in_progress'] ?? 0);
    $slaBreachedNonConformitiesCount = $this->nonConformityStatistics->countSlaBreachedNonConformities($organizationId);

    return new OrganizationWeeklyDigest(
      overdueInterventionsCount: $overdueInterventionsCount,
      overdueInterventions: 0 < $overdueInterventionsCount
        ? $this->interventionStatistics->findOverdueInterventions($organizationId, $now, self::DETAIL_LIMIT)
        : [],
      maintenanceDueSoonCount: $maintenanceDueSoonCount,
      maintenanceOverdueCount: $maintenanceOverdueCount,
      maintenanceDeadlines: 0 < $maintenanceDueSoonCount + $maintenanceOverdueCount
        ? $this->maintenanceStatistics->findDueSchedules($organizationId, $now, $windowEnd, self::DETAIL_LIMIT)
        : [],
      openNonConformitiesCount: $openNonConformitiesCount,
      slaBreachedNonConformitiesCount: $slaBreachedNonConformitiesCount,
      openNonConformities: 0 < $openNonConformitiesCount
        ? $this->nonConformityStatistics->findOpenNonConformities($organizationId, self::DETAIL_LIMIT)
        : [],
    );
  }
  // #endregion
}
