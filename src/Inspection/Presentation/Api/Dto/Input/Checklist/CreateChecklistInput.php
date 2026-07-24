<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Input\Checklist;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateChecklistInput
{
  #[Assert\NotBlank(message: 'Checklist name is required.')]
  #[Assert\Length(max: 255)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Checklist name', required: true, example: 'Contrôle Extincteur')]
  public string $name = '';

  #[Assert\NotBlank(message: 'Version is required.')]
  #[Assert\Length(max: 50)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Version label', required: true, example: '1.0')]
  public string $version = '';

  #[Assert\Length(max: 40)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional human-facing reference code, unique per organization', required: false, example: 'CHK-EXT-Q')]
  public ?string $referenceCode = null;

  /**
   * @var list<ChecklistItemInput>
   */
  #[Assert\Valid]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Checklist items')]
  public array $items = [];
}
