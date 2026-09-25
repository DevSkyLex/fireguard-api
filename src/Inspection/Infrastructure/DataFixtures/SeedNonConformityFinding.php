<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\DataFixtures;

use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;

/**
 * Identity and finding for one deterministic seeded non-conformity.
 *
 * @category DataFixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SeedNonConformityFinding
{
  public function __construct(
    public string $id,
    public InspectionRecord $inspection,
    public string $description,
    public string $severity,
    public string $status,
  ) {
  }
}
