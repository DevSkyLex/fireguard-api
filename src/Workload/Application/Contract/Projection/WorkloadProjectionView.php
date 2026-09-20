<?php

declare(strict_types=1);

namespace Workload\Application\Contract\Projection;

/**
 * Contract WorkloadProjectionView.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkloadProjectionView
{
  /**
   * @since 1.0.0
   *
   * @param string $startsOn inclusive organization-local start date
   * @param string $endsOn inclusive organization-local end date
   * @param string $today current calendar date in the organization timezone
   * @param string $timezone organization IANA timezone used to interpret local dates
   * @param string $firstDayOfWeek organization-configured first day of the week
   * @param string $calculatedAt instant at which the projection was calculated
   * @param list<MemberWorkloadView> $members
   * @param list<UnallocatedWorkView> $unassigned
   * @param string $completeness calculation quality: complete, partial, or unavailable
   */
  public function __construct(
    public string $startsOn,
    public string $endsOn,
    public string $today,
    public string $timezone,
    public string $firstDayOfWeek,
    public string $calculatedAt,
    public array $members,
    public array $unassigned,
    public string $completeness,
  ) {
  }
}
