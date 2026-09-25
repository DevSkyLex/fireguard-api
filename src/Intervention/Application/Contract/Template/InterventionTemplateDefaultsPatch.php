<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * Default site and responsible member overrides.
 */
final readonly class InterventionTemplateDefaultsPatch
{
  public function __construct(
    public ?string $siteId,
    public ?string $responsibleId,
    public bool $hasSiteId,
    public bool $hasResponsibleId,
  ) {
  }
}
