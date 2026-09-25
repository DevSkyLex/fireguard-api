<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Inspection;

use Inspection\Domain\ValueObject\{InspectionChecklistId, InspectionFacilityId};

/**
 * Optional references and text supplied when a draft inspection is created.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionCreationOptions
{
  public function __construct(
    public ?InspectionFacilityId $facilityId = null,
    public ?InspectionChecklistId $checklistId = null,
    public ?string $notes = null,
    public ?string $signature = null,
  ) {
  }
}
