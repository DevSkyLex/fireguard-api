<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationDashboard;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationDashboardQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationDashboardQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @param string $organizationId the organization identifier
   * @param string $userId the authenticated user identifier
   * @param ?string $periodFrom optional lower bound for the dashboard period
   * @param ?string $periodTo optional upper bound for the dashboard period
   * @param bool $compareWithPreviousPeriod whether previous-period comparison is included
   * @param string $granularity trend aggregation granularity (`day`, `week`, `month`, `auto`)
   * @param ?string $timeZone optional IANA timezone used for bucket boundaries and period rendering; defaults to UTC when omitted and not implied by request bounds
   * @param ?string $facilityType optional facility type filter applied to the facilities section
   * @param ?string $equipmentType optional equipment type filter applied to the equipment section
   * @param ?string $equipmentStatus optional equipment status filter applied to the equipment section
   * @param ?string $inspectionStatus optional inspection status filter applied to inspection metrics and trends
   * @param ?string $inspectionResult optional inspection result filter applied to inspection metrics and trends
   * @param ?string $inspectorType optional inspector type filter applied to inspection metrics and trends
   * @param ?string $nonConformityStatus optional non-conformity status filter applied to non-conformity metrics and trends
   * @param ?string $nonConformitySeverity optional non-conformity severity filter applied to non-conformity metrics and trends
   */
  public function __construct(
    public string $organizationId,
    public string $userId,
    public ?string $periodFrom = null,
    public ?string $periodTo = null,
    public bool $compareWithPreviousPeriod = true,
    public string $granularity = 'day',
    public ?string $timeZone = null,
    public ?string $facilityType = null,
    public ?string $equipmentType = null,
    public ?string $equipmentStatus = null,
    public ?string $inspectionStatus = null,
    public ?string $inspectionResult = null,
    public ?string $inspectorType = null,
    public ?string $nonConformityStatus = null,
    public ?string $nonConformitySeverity = null,
  ) {
  }
}
