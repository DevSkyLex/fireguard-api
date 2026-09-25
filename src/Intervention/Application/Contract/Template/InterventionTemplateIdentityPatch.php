<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * Name, description and type overrides with independent presence flags.
 */
final readonly class InterventionTemplateIdentityPatch
{
  public function __construct(
    public ?string $name,
    public ?string $description,
    public ?string $type,
    public bool $hasName,
    public bool $hasDescription,
    public bool $hasType,
  ) {
  }
}
