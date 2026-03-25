<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\EditInspection;

use Shared\Application\Message\CommandMessage;

final readonly class EditInspectionCommand implements CommandMessage
{
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public ?string $equipmentId = null,
    public ?string $facilityId = null,
    public ?string $checklistId = null,
    public ?string $result = null,
    public ?string $performedAt = null,
    public ?string $notes = null,
    public ?string $signature = null,
    public bool $hasEquipmentId = false,
    public bool $hasFacilityId = false,
    public bool $hasChecklistId = false,
    public bool $hasResult = false,
    public bool $hasPerformedAt = false,
    public bool $hasNotes = false,
    public bool $hasSignature = false,
  ) {
  }
}
