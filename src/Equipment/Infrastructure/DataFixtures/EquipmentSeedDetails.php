<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\DataFixtures;

/** Fixture-only catalogue fields and optional position on a facility plan. */
final readonly class EquipmentSeedDetails
{
  /**
   * @param array{attachmentId: string, x: float, y: float}|null $planPosition
   */
  public function __construct(
    public ?string $brand,
    public ?string $model,
    public ?string $serialNumber,
    public ?string $locationLabel,
    public ?array $planPosition = null,
  ) {
  }
}
