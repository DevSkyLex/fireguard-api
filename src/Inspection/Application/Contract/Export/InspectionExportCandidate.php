<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Export;

/**
 * Domain InspectionExportCandidate.
 *
 * A single inspection row as read from {@see \Inspection\Application\Port\Outbound\InspectionRepositoryPort::listExportCandidates()},
 * carrying only the raw `equipmentId`/`facilityId`/`checklistId` identifiers
 * — no display name is resolved yet, mirroring
 * `Intervention\Application\Contract\Export\InterventionExportCandidate`.
 * {@see \Inspection\Application\UseCase\Query\ExportInspections\ExportInspectionsHandler}
 * turns a batch of these into {@see InspectionExportRow} once the
 * facility/equipment/checklist names and non-conformity counts have been
 * resolved in bulk.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionExportCandidate
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the inspection identifier
   * @param string $equipmentId the inspected equipment identifier
   * @param ?string $facilityId the site (facility) identifier, if any
   * @param ?string $checklistId the checklist template identifier, if any
   * @param string $status the inspection status
   * @param string $result the inspection result
   * @param string $performedAt the date the inspection was performed, ISO 8601
   * @param string $createdAt the creation date, ISO 8601
   * @param string $updatedAt the last update date, ISO 8601
   */
  public function __construct(
    public string $id,
    public string $equipmentId,
    public ?string $facilityId,
    public ?string $checklistId,
    public string $status,
    public string $result,
    public string $performedAt,
    public string $createdAt,
    public string $updatedAt,
  ) {
  }
  // #endregion
}
