<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Plan;

/**
 * Optional catalog values supplied when creating a plan.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PlanCreationOptions
{
  public function __construct(
    public ?string $description = null,
    public bool $isActive = true,
    public bool $isDefault = false,
    public int $sortOrder = 0,
  ) {
  }
}
