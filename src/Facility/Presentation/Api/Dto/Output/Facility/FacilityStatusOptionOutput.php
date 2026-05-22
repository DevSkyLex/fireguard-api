<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Output\Facility;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

final class FacilityStatusOptionOutput
{
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $value = '';

  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $label = '';
}
