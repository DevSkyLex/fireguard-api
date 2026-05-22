<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto\Output\User;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use User\Presentation\Api\Serialization\UserSerializationGroup;

final class UserStatusOptionOutput
{
  #[Groups([UserSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $value = '';

  #[Groups([UserSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $label = '';
}
