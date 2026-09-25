<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Inspection;

/**
 * Result, lifecycle status and performed-time bounds for inspection reads.
 */
final readonly class InspectionExecutionCriteria
{
  public function __construct(
    public ?string $result = null,
    public ?string $status = null,
    public ?string $performedAtFrom = null,
    public ?string $performedAtTo = null,
  ) {
  }
}
