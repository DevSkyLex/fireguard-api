<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Facility;

use Facility\Domain\ValueObject\FacilityType;

/** Persisted descriptive fields of a canonical facility. */
final readonly class CanonicalFacilityContent
{
  /**
   * @param array<string, mixed> $metadata
   */
  public function __construct(
    public FacilityType $type,
    public string $name,
    public ?string $code,
    public ?string $address,
    public ?float $latitude,
    public ?float $longitude,
    public array $metadata,
  ) {
  }
}
