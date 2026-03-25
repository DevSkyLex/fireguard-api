<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Input\Inspection;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Domain\ValueObject\InspectionResult;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class EditInspectionInput
{
  #[Assert\Uuid(message: 'Equipment ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Equipment identifier', required: false)]
  public ?string $equipmentId = null;

  #[Assert\Uuid(message: 'Facility ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional facility identifier', required: false)]
  public ?string $facilityId = null;

  #[Assert\Uuid(message: 'Checklist ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional checklist identifier', required: false)]
  public ?string $checklistId = null;

  #[Assert\Choice(callback: [InspectionResult::class, 'values'])]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Inspection result', required: false, example: 'pass')]
  public ?string $result = null;

  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Date the inspection was performed (ISO 8601)', required: false, example: '2024-06-15T10:00:00+02:00')]
  public ?string $performedAt = null;

  #[Assert\Length(max: 5000)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional free-form notes', required: false)]
  public ?string $notes = null;

  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional signature data', required: false)]
  public ?string $signature = null;
}
