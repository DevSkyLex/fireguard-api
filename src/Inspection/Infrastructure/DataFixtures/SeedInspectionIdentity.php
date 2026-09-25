<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\DataFixtures;

/**
 * Identifiers and location for one deterministic seeded inspection.
 *
 * @category DataFixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SeedInspectionIdentity
{
  public function __construct(
    public string $id,
    public string $equipmentId,
    public ?string $facilityId,
    public ?string $checklistId,
  ) {
  }
}
