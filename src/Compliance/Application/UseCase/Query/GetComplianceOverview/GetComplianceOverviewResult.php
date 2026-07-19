<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\GetComplianceOverview;

use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Domain\ValueObject\ComplianceStatus;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetComplianceOverviewResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetComplianceOverviewResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $generatedAt ISO 8601 generation datetime (the register is a live snapshot, not a historical reconstruction)
   * @param ComplianceStatus $organizationStatus the worst facility status across the organization
   * @param array{
   *   totalEquipmentCount: int,
   *   activeEquipmentCount: int,
   *   upToDateEquipmentCount: int,
   *   dueSoonEquipmentCount: int,
   *   overdueEquipmentCount: int,
   *   unscheduledEquipmentCount: int,
   *   trackedEquipmentCount: int,
   *   complianceRate: ?float,
   *   openLowNonConformityCount: int,
   *   openMediumNonConformityCount: int,
   *   openHighNonConformityCount: int,
   *   openCriticalNonConformityCount: int,
   * } $totals organization-wide totals, summed across facilities. `trackedEquipmentCount` is the denominator `complianceRate` is derived from ({@see \Compliance\Application\Contract\FacilityComplianceView::computeComplianceRate()}); `complianceRate` is a percentage (0.0-100.0, 1 decimal) or null when `trackedEquipmentCount` is 0
   * @param list<FacilityComplianceView> $facilities the per-facility compliance breakdown
   */
  public function __construct(
    public string $generatedAt,
    public ComplianceStatus $organizationStatus,
    public array $totals,
    public array $facilities,
  ) {
  }
}
