<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\TrustDevice;

use DateTimeImmutable;

/**
 * Result TrustDeviceResult
 * @final
 *
 * Result of device trust operation.
 *
 * @category Result
 * @package TrustedDevice\Application\UseCase\Command\TrustDevice
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TrustDeviceResult
{
  public function __construct(
    public string $deviceId,
    public string $token,
    public string $deviceName,
    public DateTimeImmutable $expiresAt,
  ) {
  }
}
