<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * The validated descriptive fields of a new intervention template.
 */
final readonly class InterventionTemplateAttributes
{
  public function __construct(
    public string $name,
    public ?string $description,
    public string $type,
    public string $priority,
    public ?string $duration,
  ) {
  }
}
