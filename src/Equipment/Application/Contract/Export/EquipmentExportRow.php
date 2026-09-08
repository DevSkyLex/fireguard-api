<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Export;

/**
 * Domain EquipmentExportRow.
 *
 * One equipment row ready for the CSV export, carrying the facility display
 * name already resolved in bulk by {@see \Equipment\Application\UseCase\Query\ExportEquipments\ExportEquipmentsHandler}
 * through {@see \Equipment\Application\Port\Outbound\FacilityNamingPort} — the
 * Presentation-layer CSV writer only formats, it never resolves a name
 * itself. Deliberately a type distinct from {@see EquipmentExportCandidate},
 * per the module's naming convention that contract types stay distinct from
 * use case Results and from each other's intermediate shapes. Mirrors
 * `Intervention\Application\Contract\Export\InterventionExportRow`.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentExportRow
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the equipment identifier
   * @param string $type the equipment type
   * @param ?string $subType the equipment sub-type, if any
   * @param ?string $brand the equipment brand, if any
   * @param ?string $model the equipment model, if any
   * @param ?string $serialNumber the equipment serial number, if any
   * @param ?string $locationLabel the equipment location label, if any
   * @param string $status the equipment status
   * @param ?string $facilityId the assigned facility identifier, if any
   * @param ?string $facilityCode the facility's organization-scoped unique code, when the facility is set and carries one — the reimport loop's `facilityCode` column
   * @param ?string $facilityName the resolved facility display name, when the facility is set and resolvable
   * @param ?string $installedAt the installation date, ISO 8601, if any
   * @param ?string $commissionedAt the commissioning date, ISO 8601, if any
   * @param string $createdAt the creation date, ISO 8601
   * @param string $updatedAt the last update date, ISO 8601
   */
  public function __construct(
    public string $id,
    public string $type,
    public ?string $subType,
    public ?string $brand,
    public ?string $model,
    public ?string $serialNumber,
    public ?string $locationLabel,
    public string $status,
    public ?string $facilityId,
    public ?string $facilityCode,
    public ?string $facilityName,
    public ?string $installedAt,
    public ?string $commissionedAt,
    public string $createdAt,
    public string $updatedAt,
  ) {
  }
  // #endregion
}
