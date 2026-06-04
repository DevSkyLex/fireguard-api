<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\Inspection;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

final class InspectionOutput
{
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationId = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $equipmentId = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $facilityId = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $result = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $performedAt = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?InspectorOutput $inspector = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $checklistId = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $notes = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $signature = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $nonConformitiesCount = 0;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $updatedAt = '';
}
