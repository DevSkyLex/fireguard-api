<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

/** Text supplied when an intervention is created or restored. */
final readonly class InterventionContent
{
  public function __construct(public string $name, public ?string $description = null)
  {
  }
}
