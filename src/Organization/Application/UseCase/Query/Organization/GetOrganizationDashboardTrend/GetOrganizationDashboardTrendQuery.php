<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationDashboardTrend;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationDashboardTrendQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationDashboardTrendQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @param string $organizationId the organization identifier
   * @param string $userId the authenticated user identifier
   * @param string $metric requested trend metric identifier
   * @param ?string $periodFrom optional lower bound for the trend period
   * @param ?string $periodTo optional upper bound for the trend period
   * @param bool $compareWithPreviousPeriod whether previous-period comparison is included
   * @param string $granularity trend aggregation granularity (`day`, `week`, `month`, `auto`)
   * @param ?string $timeZone optional IANA timezone used for bucket boundaries and period rendering; defaults to UTC when omitted and not implied by request bounds
   * @param ?string $inspectionStatus optional inspection status filter applied to inspection trends
   * @param ?string $inspectionResult optional inspection result filter applied to inspection trends
   * @param ?string $inspectorType optional inspector type filter applied to inspection trends
   * @param ?string $nonConformityStatus optional non-conformity status filter applied to non-conformity trends
   * @param ?string $nonConformitySeverity optional non-conformity severity filter applied to non-conformity trends
   */
  public function __construct(
    public string $organizationId,
    public string $userId,
    public string $metric,
    public ?string $periodFrom = null,
    public ?string $periodTo = null,
    public bool $compareWithPreviousPeriod = true,
    public string $granularity = 'day',
    public ?string $timeZone = null,
    public ?string $inspectionStatus = null,
    public ?string $inspectionResult = null,
    public ?string $inspectorType = null,
    public ?string $nonConformityStatus = null,
    public ?string $nonConformitySeverity = null,
  ) {
  }
}
