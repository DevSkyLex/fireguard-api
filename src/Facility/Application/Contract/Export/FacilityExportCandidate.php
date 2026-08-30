<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Export;

/**
 * Domain FacilityExportCandidate.
 *
 * A single facility row as read from the organization's facilities, carrying
 * only the raw `parentFacilityId` identifier — no `parentCode` is resolved
 * yet. {@see \Facility\Application\UseCase\Query\ExportFacilities\ExportFacilitiesHandler}
 * turns a batch of these into {@see FacilityExportRow} once the parent codes
 * have been resolved in bulk. Mirrors
 * {@see \Intervention\Application\Contract\Export\InterventionExportCandidate}.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityExportCandidate
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
   * @param ?string $parentFacilityId the parent facility identifier, if any
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
    public ?string $parentFacilityId,
    public string $status,
    public string $createdAt,
    public string $updatedAt,
    public ?int $levelIndex = null,
  ) {
  }
  // #endregion
}
