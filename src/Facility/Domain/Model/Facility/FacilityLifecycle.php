<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Facility;

use DateTimeImmutable;
use Facility\Domain\ValueObject\FacilityStatus;

/** Persisted status and timestamps of a facility aggregate. */
final readonly class FacilityLifecycle
{
  public function __construct(
    public FacilityStatus $status,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
}
