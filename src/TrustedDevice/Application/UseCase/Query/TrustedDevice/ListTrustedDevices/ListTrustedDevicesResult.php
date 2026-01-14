<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices;

use Shared\Application\Message\ResultMessage;

/**
 * Result ListTrustedDevicesResult.
 */
final readonly class ListTrustedDevicesResult implements ResultMessage
{
  /**
   * @param list<TrustedDeviceItemResult> $devices
   */
  public function __construct(
    public array $devices,
  ) {
  }
}
