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
  public function __construct(
    public ?string $type = null,
    public ?string $status = null,
    public ?string $parentFacilityId = null,
    public ?string $code = null,
    public ?string $search = null,
    public bool $rootsOnly = false,
    public ?bool $hasCoordinates = null,
  ) {
  }
}
