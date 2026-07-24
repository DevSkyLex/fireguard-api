<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Factory;

use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Presentation\Api\Dto\Output\ComplianceSummaryOutput;

use function array_map;

/**
 * Factory ComplianceSummaryOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ComplianceSummaryOutputFactory
{
  // #region Methods
  /**
   * Method fromOrganizationRegister.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $generatedAt ISO 8601 generation datetime
   * @param string $organizationStatus the organization rollup status value
   * @param array<string, int|float|null> $totals organization-wide totals (already includes `trackedEquipmentCount`/`complianceRate`, computed by the handler)
   * @param list<FacilityComplianceView> $facilities the per-facility compliance views
   *
   * @return ComplianceSummaryOutput the organization rollup output
   */
  public function fromOrganizationRegister(string $organizationId, string $generatedAt, string $organizationStatus, array $totals, array $facilities): ComplianceSummaryOutput
  {
    $output = new ComplianceSummaryOutput();
    $output->organizationId = $organizationId;
    $output->facilityId = null;
    $output->generatedAt = $generatedAt;
    $output->organizationStatus = $organizationStatus;
    $output->totals = $totals;
    $output->facilities = array_map($this->facilityRow(...), $facilities);

    return $output;
  }

  /**
   * Method fromFacilityRegister.
   *
   * Builds the single-facility variant: `facilities` holds exactly one
   * entry and `organizationStatus`/`totals` reflect that facility alone.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $generatedAt ISO 8601 generation datetime
   * @param FacilityComplianceView $facility the single-facility compliance view
   *
   * @return ComplianceSummaryOutput the single-facility detail output
   */
  public function fromFacilityRegister(string $organizationId, string $generatedAt, FacilityComplianceView $facility): ComplianceSummaryOutput
  {
    $output = new ComplianceSummaryOutput();
    $output->organizationId = $organizationId;
    $output->facilityId = $facility->facilityId;
    $output->generatedAt = $generatedAt;
    $output->organizationStatus = $facility->status->value;
    $output->totals = [
      'totalEquipmentCount' => $facility->totalEquipmentCount,
      'activeEquipmentCount' => $facility->activeEquipmentCount,
      'upToDateEquipmentCount' => $facility->upToDateEquipmentCount,
      'dueSoonEquipmentCount' => $facility->dueSoonEquipmentCount,
      'overdueEquipmentCount' => $facility->overdueEquipmentCount,
      'unscheduledEquipmentCount' => $facility->unscheduledEquipmentCount,
      'trackedEquipmentCount' => $facility->trackedEquipmentCount(),
      'complianceRate' => $facility->complianceRate(),
      'openLowNonConformityCount' => $facility->openLowNonConformityCount,
      'openMediumNonConformityCount' => $facility->openMediumNonConformityCount,
      'openHighNonConformityCount' => $facility->openHighNonConformityCount,
      'openCriticalNonConformityCount' => $facility->openCriticalNonConformityCount,
    ];
    $output->facilities = [$this->facilityRow($facility)];

    return $output;
  }

  /**
   * Method facilityRow.
   *
   * @since 1.0.0
   *
   * @param FacilityComplianceView $facility the facility compliance view
   *
   * @return array{
   *   facilityId: string,
   *   name: string,
   *   type: string,
   *   parentFacilityId: ?string,
   *   path: string,
   *   status: string,
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
   *   lastInspectionAt: ?string,
   * } the facility row
   */
  private function facilityRow(FacilityComplianceView $facility): array
  {
    return [
      'facilityId' => $facility->facilityId,
      'name' => $facility->name,
      'type' => $facility->type,
      'parentFacilityId' => $facility->parentFacilityId,
      'path' => $facility->path,
      'status' => $facility->status->value,
      'totalEquipmentCount' => $facility->totalEquipmentCount,
      'activeEquipmentCount' => $facility->activeEquipmentCount,
      'upToDateEquipmentCount' => $facility->upToDateEquipmentCount,
      'dueSoonEquipmentCount' => $facility->dueSoonEquipmentCount,
      'overdueEquipmentCount' => $facility->overdueEquipmentCount,
      'unscheduledEquipmentCount' => $facility->unscheduledEquipmentCount,
      'trackedEquipmentCount' => $facility->trackedEquipmentCount(),
      'complianceRate' => $facility->complianceRate(),
      'openLowNonConformityCount' => $facility->openLowNonConformityCount,
      'openMediumNonConformityCount' => $facility->openMediumNonConformityCount,
      'openHighNonConformityCount' => $facility->openHighNonConformityCount,
      'openCriticalNonConformityCount' => $facility->openCriticalNonConformityCount,
      'lastInspectionAt' => $facility->lastInspectionAt,
    ];
  }
  // #endregion
}
