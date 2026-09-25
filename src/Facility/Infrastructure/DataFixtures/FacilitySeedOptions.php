<?php

declare(strict_types=1);

namespace Facility\Infrastructure\DataFixtures;

use Facility\Domain\ValueObject\FacilityStatus;

/** Optional location and lifecycle data of one fixture facility. */
final readonly class FacilitySeedOptions
{
  /**
   * @param array<string, mixed> $metadata
   * @param array{attachmentId: string, points: list<array{float, float}>}|null $planGeometry
   */
  public function __construct(
    public ?string $address = null,
    public array $metadata = [],
    public ?float $latitude = null,
    public ?float $longitude = null,
    public string $status = FacilityStatus::ACTIVE->value,
    public ?int $levelIndex = null,
    public ?array $planGeometry = null,
  ) {
  }
}
