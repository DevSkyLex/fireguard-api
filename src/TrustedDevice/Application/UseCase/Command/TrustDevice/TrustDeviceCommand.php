<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\TrustDevice;

/**
 * Command TrustDeviceCommand
 * @final
 *
 * Command to register a trusted device.
 *
 * @category Command
 * @package TrustedDevice\Application\UseCase\Command\TrustDevice
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TrustDeviceCommand
{
  public function __construct(
    public string $userId,
    public string $userAgent,
    public ?string $ipAddress = null,
    public ?string $acceptLanguage = null,
    public int $ttlDays = 30,
  ) {
  }
}
