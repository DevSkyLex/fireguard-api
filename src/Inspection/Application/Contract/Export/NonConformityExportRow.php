<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Export;

/**
 * Domain NonConformityExportRow.
 *
 * One non-conformity row ready for the CSV export, carrying the facility and
 * equipment display names already resolved in bulk, and the age in days
 * already computed against {@see \Shared\Application\Port\Outbound\ClockPort}
 * by {@see \Inspection\Application\UseCase\Query\ExportNonConformities\ExportNonConformitiesHandler}.
 * The Presentation-layer CSV writer only formats, it never resolves a name
 * or computes a date difference itself. Deliberately a type distinct from
 * {@see NonConformityExportCandidate}, per the module's naming convention
 * that contract types stay distinct from use case Results and from each
 * other's intermediate shapes.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformityExportRow
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the non-conformity identifier
   * @param string $severity the non-conformity severity
   * @param string $status the non-conformity status
   * @param int $ageInDays the number of whole days elapsed since `createdAt`, measured against the clock
   * @param ?string $facilityId the site (facility) identifier of the owning inspection, if any
   * @param ?string $facilityName the resolved facility display name, when resolvable
   * @param ?string $equipmentId the equipment identifier of the owning inspection, if any
   * @param ?string $equipmentSerialNumber the resolved equipment serial number, when resolvable
   * @param string $inspectionId the owning inspection identifier
   * @param string $createdAt the creation date, ISO 8601
   * @param ?string $resolvedAt the resolution date, ISO 8601, if any
   */
  public function __construct(
    public string $id,
    public string $severity,
    public string $status,
    public int $ageInDays,
    public ?string $facilityId,
    public ?string $facilityName,
    public ?string $equipmentId,
    public ?string $equipmentSerialNumber,
    public string $inspectionId,
    public string $createdAt,
    public ?string $resolvedAt,
  ) {
  }
  // #endregion
}
