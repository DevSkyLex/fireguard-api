<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\DataFixtures;

use DateTimeImmutable;

/**
 * Inspector, result and lifecycle for one deterministic seeded inspection.
 *
 * @category DataFixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SeedInspectionObservation
{
  public function __construct(
    public string $inspectorType,
    public string $inspectorName,
    public string $result,
    public string $status,
    public DateTimeImmutable $performedAt,
    public ?string $inspectorUserId = null,
    public ?string $inspectorOrganizationName = null,
  ) {
  }
}
