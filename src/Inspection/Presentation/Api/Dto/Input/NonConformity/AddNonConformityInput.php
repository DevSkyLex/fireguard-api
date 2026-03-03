<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Input\NonConformity;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Domain\ValueObject\NonConformitySeverity;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class AddNonConformityInput
{
  #[Assert\NotBlank(message: 'Description is required.')]
  #[Assert\Length(max: 2000)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Non-conformity description', required: true, example: 'Extincteur hors service')]
  public string $description = '';

  #[Assert\NotBlank(message: 'Severity is required.')]
  #[Assert\Choice(callback: [NonConformitySeverity::class, 'values'])]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Severity level', required: true, example: 'high')]
  public string $severity = '';

  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional due date for resolution (ISO 8601)', required: false, example: '2024-07-15T00:00:00+02:00')]
  public ?string $dueAt = null;

  #[Assert\Length(max: 5000)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional notes', required: false)]
  public ?string $notes = null;
}
