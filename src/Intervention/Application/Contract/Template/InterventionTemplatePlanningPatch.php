<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * Priority and duration overrides with independent presence flags.
 */
final readonly class InterventionTemplatePlanningPatch
{
  public function __construct(
    public ?string $priority,
    public ?string $duration,
    public bool $hasPriority,
    public bool $hasDuration,
  ) {
  }
}
