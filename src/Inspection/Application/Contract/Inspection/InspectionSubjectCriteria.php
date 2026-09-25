<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Inspection;

/**
 * The inspected equipment, facility and checklist filters.
 */
final readonly class InspectionSubjectCriteria
{
  public function __construct(
    public ?string $equipmentId = null,
    public ?string $facilityId = null,
    public ?string $checklistId = null,
  ) {
  }
}
