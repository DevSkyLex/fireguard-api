<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Dto\Output\TrustedDevice;

use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;
use TrustedDevice\Presentation\Api\Serialization\TrustedDeviceSerializationGroup;

/**
 * DTO TrustDeviceOutput.
 */
final class TrustDeviceOutput
{
  #[Groups([TrustedDeviceSerializationGroup::READ])]
  public string $deviceId;

  #[Groups([TrustedDeviceSerializationGroup::READ])]
  public string $token;

  #[Groups([TrustedDeviceSerializationGroup::READ])]
  public string $deviceName;

  #[Groups([TrustedDeviceSerializationGroup::READ])]
  public DateTimeImmutable $expiresAt;
}
