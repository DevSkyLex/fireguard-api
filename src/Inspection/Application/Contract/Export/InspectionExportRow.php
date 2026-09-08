<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Export;

/**
 * Domain InspectionExportRow.
 *
 * One inspection row ready for the CSV export, carrying the facility,
 * equipment, and checklist display names — plus the bulk-resolved
 * non-conformity counters — already resolved by
 * {@see \Inspection\Application\UseCase\Query\ExportInspections\ExportInspectionsHandler}.
 * The Presentation-layer CSV writer only formats, it never resolves a name
 * itself. Deliberately a type distinct from {@see InspectionExportCandidate},
 * per the module's naming convention that contract types stay distinct from
 * use case Results and from each other's intermediate shapes.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionExportRow
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the inspection identifier
   * @param string $status the inspection status
   * @param string $result the inspection result
   * @param ?string $facilityId the site (facility) identifier, if any
   * @param ?string $facilityName the resolved facility display name, when the site is set and resolvable
   * @param string $equipmentId the inspected equipment identifier
   * @param ?string $equipmentSerialNumber the resolved equipment serial number, when resolvable
   * @param ?string $checklistId the checklist template identifier, if any
   * @param ?string $checklistName the resolved checklist display name, when the checklist is set and resolvable
   * @param string $performedAt the date the inspection was performed, ISO 8601
   * @param int $nonConformitiesOpen the number of non-conformities still open or in progress
   * @param int $nonConformitiesTotal the total number of non-conformities recorded on the inspection
   * @param string $createdAt the creation date, ISO 8601
   * @param string $updatedAt the last update date, ISO 8601
   */
  public function __construct(
    public string $id,
    public string $status,
    public string $result,
    public ?string $facilityId,
    public ?string $facilityName,
    public string $equipmentId,
    public ?string $equipmentSerialNumber,
    public ?string $checklistId,
    public ?string $checklistName,
    public string $performedAt,
    public int $nonConformitiesOpen,
    public int $nonConformitiesTotal,
    public string $createdAt,
    public string $updatedAt,
  ) {
  }
  // #endregion
}
