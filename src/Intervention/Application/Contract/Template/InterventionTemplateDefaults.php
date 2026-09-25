<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * Optional site and responsible member copied into future interventions.
 */
final readonly class InterventionTemplateDefaults
{
  public function __construct(
    public ?string $siteId,
    public ?string $responsibleId,
  ) {
  }
}
