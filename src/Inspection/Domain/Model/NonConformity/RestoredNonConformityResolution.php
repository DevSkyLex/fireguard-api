<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\NonConformity;

use DateTimeImmutable;
use Inspection\Domain\ValueObject\NonConformityStatus;

/** Persisted resolution state, including dates that may be absent independently. */
final readonly class RestoredNonConformityResolution
{
  public function __construct(
    public NonConformityStatus $status,
    public ?DateTimeImmutable $dueAt = null,
    public ?DateTimeImmutable $resolvedAt = null,
    public ?string $notes = null,
  ) {
  }
}
