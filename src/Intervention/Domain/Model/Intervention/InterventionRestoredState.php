<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

use DateTimeImmutable;
use Intervention\Domain\ValueObject\InterventionStatus;

/** Persisted lifecycle fields supplied alongside the intervention definition. */
final readonly class InterventionRestoredState
{
  public function __construct(
    public InterventionCreation $creation,
    public InterventionStatus $status,
    public ?string $reviewNote,
    public int $revision,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
}
