<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

use DateTimeImmutable;

/**
 * Rule overrides and their merge-patch presence flags.
 */
final readonly class InterventionRecurrenceCadencePatch
{
  public function __construct(
    public ?string $frequency,
    public ?int $interval,
    public ?DateTimeImmutable $anchorDate,
    public bool $hasFrequency,
    public bool $hasInterval,
    public bool $hasAnchorDate,
  ) {
  }
}
