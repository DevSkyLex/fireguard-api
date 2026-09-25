<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Findings supplied for one draft inspection edit.
 *
 * Presence is independent of value: null clears notes or signature, whereas
 * null for result or performedAt leaves their required stored value intact.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionFindingPatch
{
  public function __construct(
    public ?InspectionResult $result = null,
    public bool $hasResult = false,
    public ?DateTimeImmutable $performedAt = null,
    public bool $hasPerformedAt = false,
    public ?InspectionTextPatch $text = null,
  ) {
  }
}
