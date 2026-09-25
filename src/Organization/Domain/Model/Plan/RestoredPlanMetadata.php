<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Plan;

/**
 * Persisted plan catalog metadata, including the historical defaults.
 */
final readonly class RestoredPlanMetadata
{
  public function __construct(
    public ?string $description = null,
    public bool $isActive = true,
    public bool $isDefault = false,
    public int $sortOrder = 0,
  ) {
  }
}
