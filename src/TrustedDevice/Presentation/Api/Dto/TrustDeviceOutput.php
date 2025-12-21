<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Dto;

use DateTimeImmutable;

/**
 * DTO TrustDeviceOutput.
 */
final class TrustDeviceOutput
{
  public string $deviceId;

  public string $token;

  public string $deviceName;

  public DateTimeImmutable $expiresAt;
}
