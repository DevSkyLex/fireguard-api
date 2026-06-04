<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\Inspection;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

final class InspectorOutput
{
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $type = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $id = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $firstName = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $lastName = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $displayName = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $avatarUrl = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $organizationName = null;
}
