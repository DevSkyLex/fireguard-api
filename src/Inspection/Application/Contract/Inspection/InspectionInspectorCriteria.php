<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Inspection;

/**
 * Inspector identity and type filters for inspection reads.
 */
final readonly class InspectionInspectorCriteria
{
  public function __construct(
    public ?string $userId = null,
    public ?string $type = null,
  ) {
  }
}
