<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

/** Optional note and signature edits with independent presence flags. */
final readonly class InspectionTextPatch
{
  public function __construct(
    public ?string $notes = null,
    public bool $hasNotes = false,
    public ?string $signature = null,
    public bool $hasSignature = false,
  ) {
  }
}
