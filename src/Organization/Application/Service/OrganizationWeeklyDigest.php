<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Contract\Inspection\OpenNonConformitySummary;
use Organization\Application\Contract\Intervention\RecentInterventionSummary;
use Organization\Application\Contract\Maintenance\MaintenanceDueSummary;

/**
 * Service OrganizationWeeklyDigest.
 *
 * One organization's weekly digest snapshot: the counters of the three
 * attention areas (overdue interventions, maintenance deadlines, unresolved
 * non-conformities) plus a bounded sample of detail lines per section.
 * A digest whose counters are all zero is never sent.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationWeeklyDigest
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $overdueInterventionsCount the overdue field intervention count
   * @param list<RecentInterventionSummary> $overdueInterventions overdue intervention detail sample
   * @param int $maintenanceDueSoonCount the maintenance deadlines due within the window
   * @param int $maintenanceOverdueCount the maintenance deadlines already past
   * @param list<MaintenanceDueSummary> $maintenanceDeadlines maintenance deadline detail sample
   * @param int $openNonConformitiesCount the unresolved non-conformity count
   * @param int $slaBreachedNonConformitiesCount the unresolved non-conformities past their resolution SLA
   * @param list<OpenNonConformitySummary> $openNonConformities unresolved non-conformity detail sample
   */
  public function __construct(
    public int $overdueInterventionsCount,
    public array $overdueInterventions,
    public int $maintenanceDueSoonCount,
    public int $maintenanceOverdueCount,
    public array $maintenanceDeadlines,
    public int $openNonConformitiesCount,
    public int $slaBreachedNonConformitiesCount,
    public array $openNonConformities,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method isEmpty.
   *
   * True when every counter is zero — nothing needs attention, so no digest
   * email is sent (deliberate silence).
   *
   * @since 1.0.0
   *
   * @return bool whether the digest carries nothing to report
   */
  public function isEmpty(): bool
  {
    return 0 === $this->overdueInterventionsCount
      && 0 === $this->maintenanceDueSoonCount
      && 0 === $this->maintenanceOverdueCount
      && 0 === $this->openNonConformitiesCount
      && 0 === $this->slaBreachedNonConformitiesCount;
  }
  // #endregion
}
