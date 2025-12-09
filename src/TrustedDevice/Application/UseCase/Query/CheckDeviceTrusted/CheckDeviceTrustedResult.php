<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\CheckDeviceTrusted;

/**
 * Result CheckDeviceTrustedResult
 * @final
 *
 * Result of device trust check.
 *
 * @category Result
 * @package TrustedDevice\Application\UseCase\Query\CheckDeviceTrusted
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CheckDeviceTrustedResult
{
  public function __construct(
    public bool $trusted,
    public ?string $deviceId = null,
    public ?string $deviceName = null,
  ) {
  }

  public static function trusted(string $deviceId, string $deviceName): self
  {
    return new self(
      trusted: true,
      deviceId: $deviceId,
      deviceName: $deviceName,
    );
  }

  public static function notTrusted(): self
  {
    return new self(trusted: false);
  }
}
