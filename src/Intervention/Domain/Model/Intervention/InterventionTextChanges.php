<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

/** Text edits with explicit presence flags for merge-patch null semantics. */
final readonly class InterventionTextChanges
{
  public function __construct(
    public ?string $name = null,
    public ?string $description = null,
    public ?string $reviewNote = null,
    public bool $hasName = false,
    public bool $hasDescription = false,
    public bool $hasReviewNote = false,
  ) {
  }
}
