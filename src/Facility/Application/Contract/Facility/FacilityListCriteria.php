<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Facility;

/**
 * Filters shared by facility listing and its matching count.
 *
 * Archived visibility remains an explicit repository scope argument; a
 * specified status takes precedence over that default visibility filter.
 */
final readonly class FacilityListCriteria
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries optional filters for facility listing while keeping archived visibility as an explicit repository scope.
   *
   * @access public
   *
   * @param ?string $type optional facility type filter
   * @param ?string $status optional facility status filter
   * @param ?string $parentFacilityId optional parent facility filter
   * @param ?string $code optional facility code filter
   * @param ?string $search optional text search
   * @param bool $rootsOnly whether to list only root facilities
   * @param ?bool $hasCoordinates whether both coordinates must be present or absent, when specified
   * @param ?list<string> $eligibleParentIds optional hierarchy-approved candidate identifiers; empty excludes every row
   * @param ?string $parentInterventionId optional authorized draft parent scope
   * @param bool $includePublishedParents whether published candidates are readable
   *
   * @return void
   */
  public function __construct(
    public ?string $type = null,
    public ?string $status = null,
    public ?string $parentFacilityId = null,
    public ?string $code = null,
    public ?string $search = null,
    public bool $rootsOnly = false,
    public ?bool $hasCoordinates = null,
    public ?array $eligibleParentIds = null,
    public ?string $parentInterventionId = null,
    public bool $includePublishedParents = true,
    public ?string $customerId = null,
  ) {
  }
  // #endregion
}
