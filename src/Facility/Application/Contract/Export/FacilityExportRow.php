<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Export;

/**
 * Domain FacilityExportRow.
 *
 * One facility row ready for the CSV export, carrying the parent facility's
 * `code` already resolved in bulk by
 * {@see \Facility\Application\UseCase\Query\ExportFacilities\ExportFacilitiesHandler}
 * through {@see \Facility\Application\Port\Outbound\FacilityRepositoryPort::getFacilityCodesByIds()} —
 * the Presentation-layer CSV writer only formats, it never resolves a code
 * itself. `parentCode` (not `parentFacilityId`) is the import round-trip
 * contract: {@see \Import\Application\Service\FacilityRowFactory} resolves a
 * parent by its `code`, not its identifier. Deliberately a type distinct
 * from {@see FacilityExportCandidate}, per the module's naming convention
 * that contract types stay distinct from use case Results and from each
 * other's intermediate shapes. Mirrors
 * {@see \Intervention\Application\Contract\Export\InterventionExportRow}.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityExportRow
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the facility identifier
   * @param string $type the facility type
   * @param string $name the facility name
   * @param ?string $code the facility code, if any
   * @param ?string $address the facility address, if any
   * @param ?float $latitude the facility latitude, if any
   * @param ?float $longitude the facility longitude, if any
   * @param ?string $parentCode the parent facility's own code, if the facility has a parent and it is resolvable
   * @param string $status the facility status
   * @param string $createdAt the creation date, ISO 8601
   * @param string $updatedAt the last update date, ISO 8601
   * @param ?int $levelIndex the stacking order of the floor, if any (ground floor = 0, first basement = -1)
   */
  public function __construct(
    public string $id,
    public string $type,
    public string $name,
    public ?string $code,
    public ?string $address,
    public ?float $latitude,
    public ?float $longitude,
    public ?string $parentCode,
    public string $status,
    public string $createdAt,
    public string $updatedAt,
    public ?int $levelIndex = null,
  ) {
  }
  // #endregion
}
