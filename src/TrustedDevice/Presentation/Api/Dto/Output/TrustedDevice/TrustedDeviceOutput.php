<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Dto\Output\TrustedDevice;

use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;
use TrustedDevice\Presentation\Api\Serialization\TrustedDeviceSerializationGroup;

/**
 * DTO TrustedDeviceOutput.
 */
final class TrustedDeviceOutput
{
  #[Groups([TrustedDeviceSerializationGroup::READ])]
  public string $id;

  #[Groups([TrustedDeviceSerializationGroup::READ])]
  public string $name;

  #[Groups([TrustedDeviceSerializationGroup::READ])]
  public DateTimeImmutable $lastUsedAt;

  #[Groups([TrustedDeviceSerializationGroup::READ])]
  public DateTimeImmutable $expiresAt;

  #[Groups([TrustedDeviceSerializationGroup::READ])]
  public DateTimeImmutable $createdAt;
}
