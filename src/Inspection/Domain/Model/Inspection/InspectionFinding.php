<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Inspection;

use DateTimeImmutable;
use Inspection\Domain\ValueObject\{InspectionResult, InspectionStatus};

/** Finding and lifecycle state, with restored text kept as persisted. */
final readonly class InspectionFinding
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
