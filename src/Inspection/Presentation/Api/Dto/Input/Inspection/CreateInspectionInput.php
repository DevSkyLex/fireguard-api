<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Input\Inspection;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Domain\ValueObject\{InspectionResult, InspectorType};
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateInspectionInput
{
  #[Assert\NotBlank(message: 'Equipment ID is required.')]
  #[Assert\Uuid(message: 'Equipment ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Equipment identifier', required: true, example: '550e8400-e29b-41d4-a716-446655440000')]
  public string $equipmentId = '';

  #[Assert\NotBlank(message: 'Result is required.')]
  #[Assert\Choice(callback: [InspectionResult::class, 'values'])]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Inspection result', required: true, example: 'pass')]
  public string $result = '';

  #[Assert\NotBlank(message: 'Performed at date is required.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Date the inspection was performed (ISO 8601)', required: true, example: '2024-06-15T10:00:00+02:00')]
  public string $performedAt = '';

  #[Assert\NotBlank(message: 'Inspector type is required.')]
  #[Assert\Choice(callback: [InspectorType::class, 'values'])]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Inspector type (user or external)', required: true, example: 'user')]
  public string $inspectorType = '';

  #[Assert\NotBlank(message: 'Inspector name is required.')]
  #[Assert\Length(max: 255)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Inspector display name', required: true, example: 'Jean Dupont')]
  public string $inspectorName = '';

  #[Assert\Uuid(message: 'Facility ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional facility identifier', required: false)]
  public ?string $facilityId = null;

  #[Assert\Uuid(message: 'Checklist ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional checklist identifier', required: false)]
  public ?string $checklistId = null;

  #[Assert\Uuid(message: 'Inspector user ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional user identifier (required for user type)', required: false)]
  public ?string $inspectorUserId = null;

  #[Assert\Length(max: 255)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional external organization name', required: false)]
  public ?string $inspectorOrganizationName = null;

  #[Assert\Length(max: 5000)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional free-form notes', required: false)]
  public ?string $notes = null;

  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional signature data', required: false)]
  public ?string $signature = null;
}
