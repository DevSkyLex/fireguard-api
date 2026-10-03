<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Facility;

use Facility\Domain\ValueObject\{FacilityCoordinates, FacilityId};

/** Optional placement and descriptive fields of a facility aggregate. */
final readonly class FacilityDetails
{
  /**
   * @param ?float $elevationMeters optional physical floor elevation in meters
   * @param ?float $heightMeters optional physical floor height in meters
   * @param array<string, mixed> $metadata
   */
  public function __construct(
    public ?FacilityId $parentFacilityId = null,
    public ?string $code = null,
    public ?string $address = null,
    public array $metadata = [],
    public ?FacilityCoordinates $coordinates = null,
    public ?int $levelIndex = null,
    public ?float $elevationMeters = null,
    public ?float $heightMeters = null,
  ) {
  }
}
