<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\NonConformity;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

final class NonConformityOutput
{
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $inspectionId = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $description = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $severity = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $dueAt = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $resolvedAt = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $notes = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $updatedAt = '';
}
