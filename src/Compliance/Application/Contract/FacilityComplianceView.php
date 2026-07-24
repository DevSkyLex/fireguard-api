<?php

declare(strict_types=1);

namespace Compliance\Application\Contract;

use Compliance\Domain\ValueObject\ComplianceStatus;

use function round;

/**
 * Contract FacilityComplianceView.
 *
 * The assembled per-facility compliance register row: identity, the graded
 * {@see ComplianceStatus} verdict, and every raw driver count the verdict is
 * derived from, so the JSON contract and the exported PDF remain
 * transparent and auditable.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityComplianceView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $facilityId the facility identifier, or `unassigned` for the synthetic no-facility bucket
   * @param string $name the facility display name
   * @param string $type the facility type
   * @param ?string $parentFacilityId the parent facility identifier, if any
   * @param string $path the facility's breadcrumb path (`Parent / Child`), or its own name at the root
   * @param ComplianceStatus $status the graded compliance verdict
   * @param int $totalEquipmentCount total published equipment assigned to the facility
   * @param int $activeEquipmentCount non-decommissioned equipment assigned to the facility
   * @param int $upToDateEquipmentCount equipment with an up-to-date maintenance schedule
   * @param int $dueSoonEquipmentCount equipment due for inspection within the reminder window
   * @param int $overdueEquipmentCount equipment overdue for inspection
   * @param int $unscheduledEquipmentCount equipment with no effective periodicity
   * @param int $openLowNonConformityCount open low-severity non-conformities
   * @param int $openMediumNonConformityCount open medium-severity non-conformities
   * @param int $openHighNonConformityCount open high-severity non-conformities
   * @param int $openCriticalNonConformityCount open critical-severity non-conformities
   * @param ?string $lastInspectionAt ISO 8601 datetime of the last closed inspection feeding this facility's schedules, if any
   */
  public function __construct(
    public string $facilityId,
    public string $name,
    public string $type,
    public ?string $parentFacilityId,
    public string $path,
    public ComplianceStatus $status,
    public int $totalEquipmentCount,
    public int $activeEquipmentCount,
    public int $upToDateEquipmentCount,
    public int $dueSoonEquipmentCount,
    public int $overdueEquipmentCount,
    public int $unscheduledEquipmentCount,
    public int $openLowNonConformityCount,
    public int $openMediumNonConformityCount,
    public int $openHighNonConformityCount,
    public int $openCriticalNonConformityCount,
    public ?string $lastInspectionAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method trackedEquipmentCount.
   *
   * Equipment actually subject to a periodicity (excludes `unscheduled`
   * equipment, which is tracked by Maintenance but carries no effective
   * periodicity to grade against).
   *
   * @since 1.0.0
   *
   * @return int the tracked equipment count
   */
  public function trackedEquipmentCount(): int
  {
    return $this->upToDateEquipmentCount + $this->dueSoonEquipmentCount + $this->overdueEquipmentCount;
  }

  /**
   * Method complianceRate.
   *
   * The facility's compliance percentage, delegated to
   * {@see self::computeComplianceRate()} so the org-wide rollup (computed
   * from summed totals, not from a `FacilityComplianceView` instance) shares
   * the exact same formula and cannot drift.
   *
   * @since 1.0.0
   *
   * @return ?float the compliance rate as a percentage (0.0-100.0, 1 decimal), or null when undefined (no tracked equipment)
   */
  public function complianceRate(): ?float
  {
    return self::computeComplianceRate($this->upToDateEquipmentCount, $this->trackedEquipmentCount());
  }

  /**
   * Method computeComplianceRate.
   *
   * Canonical compliance-rate formula, shared by the per-facility row
   * ({@see self::complianceRate()}) and the organization-wide rollup
   * (`GetComplianceOverviewHandler`'s summed totals):
   *
   *     complianceRate = upToDateEquipmentCount / trackedEquipmentCount * 100
   *
   * Expressed as a percentage in the `[0.0, 100.0]` range, rounded to 1
   * decimal place.
   *
   * The denominator is `trackedEquipmentCount` (up-to-date + due-soon +
   * overdue), NOT `totalEquipmentCount` or `activeEquipmentCount` — only
   * equipment actually subject to a periodicity can be graded up-to-date vs
   * not; `unscheduled` equipment carries no effective periodicity to grade
   * against.
   *
   * When `trackedEquipmentCount` is `0` the rate is UNDEFINED, not `0`: a
   * site with no tracked equipment is graded `not_applicable` by
   * {@see \Compliance\Application\Service\ComplianceStatusPolicy}, and
   * reporting "0%" on a regulatory compliance document would misrepresent an
   * "nothing to assess" site as a failing one. Callers MUST treat `null` as
   * "no rate to display", never coerce it to `0`.
   *
   * @since 1.0.0
   *
   * @param int $upToDateEquipmentCount equipment with an up-to-date maintenance schedule
   * @param int $trackedEquipmentCount equipment actually subject to a periodicity (up-to-date + due-soon + overdue)
   *
   * @return ?float the compliance rate as a percentage (0.0-100.0, 1 decimal), or null when `$trackedEquipmentCount` is 0
   */
  public static function computeComplianceRate(int $upToDateEquipmentCount, int $trackedEquipmentCount): ?float
  {
    if (0 === $trackedEquipmentCount) {
      return null;
    }

    return round($upToDateEquipmentCount / $trackedEquipmentCount * 100, 1);
  }
  // #endregion
}
