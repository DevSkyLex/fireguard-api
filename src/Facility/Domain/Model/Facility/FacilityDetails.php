<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Facility;

use Facility\Domain\ValueObject\{FacilityCoordinates, FacilityId};

/** Optional placement and descriptive fields of a facility aggregate. */
final readonly class FacilityDetails
{
  /**
   * @param array<string, mixed> $metadata
   */
  public function __construct(
    public ?FacilityId $parentFacilityId = null,
    public ?string $code = null,
    public ?string $address = null,
    public array $metadata = [],
    public ?FacilityCoordinates $coordinates = null,
    public ?int $levelIndex = null,
  ) {
  }
}
