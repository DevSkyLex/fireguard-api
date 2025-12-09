<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\RevokeAllDevices;

/**
 * Command RevokeAllDevicesCommand
 * @final
 */
final readonly class RevokeAllDevicesCommand
{
  public function __construct(
    public string $userId,
  ) {
  }
}
