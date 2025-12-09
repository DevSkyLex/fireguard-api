<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\RevokeDevice;

/**
 * Command RevokeDeviceCommand
 * @final
 */
final readonly class RevokeDeviceCommand
{
  public function __construct(
    public string $deviceId,
    public string $userId,
  ) {
  }
}
