<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Inspection;

use DateTimeImmutable;
use Inspection\Domain\ValueObject\{InspectionResult, InspectionStatus};

/** Persisted finding and lifecycle state without normalization on reload. */
final readonly class RestoredInspectionFinding
{
  public function __construct(
    public InspectionResult $result,
    public InspectionStatus $status,
    public DateTimeImmutable $performedAt,
    public ?string $notes = null,
    public ?string $signature = null,
  ) {
  }
}
