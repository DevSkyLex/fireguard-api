<?php

declare(strict_types=1);

namespace Facility\Infrastructure\DataFixtures;

use DateTimeImmutable;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;

/** Required identity and description of one fixture facility. */
final readonly class FacilitySeed
{
  public function __construct(
    public string $id,
    public ?FacilityRecord $parentFacility,
    public string $type,
    public string $name,
    public ?string $code,
    public DateTimeImmutable $createdAt,
  ) {
  }
}
