<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

/**
 * Identity overrides and their merge-patch presence flags.
 */
final readonly class InterventionRecurrenceIdentityPatch
{
  public function __construct(
    public ?string $name,
    public ?string $siteId,
    public ?string $responsibleId,
    public bool $hasName,
    public bool $hasSiteId,
    public bool $hasResponsibleId,
  ) {
  }
}
