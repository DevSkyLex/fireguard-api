<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Facility;

use DateTimeImmutable;
use Facility\Domain\ValueObject\FacilityStatus;

/** Persisted lifecycle and revision fields of a canonical facility. */
final readonly class CanonicalFacilityVersion
{
  public function __construct(
    public FacilityStatus $status,
    public int $revision,
    public DateTimeImmutable $updatedAt,
    public ?int $levelIndex = null,
  ) {
  }
}
