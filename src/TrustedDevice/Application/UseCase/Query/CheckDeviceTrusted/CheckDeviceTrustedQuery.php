<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\CheckDeviceTrusted;

/**
 * Query CheckDeviceTrustedQuery
 * @final
 *
 * Query to check if a device is trusted.
 *
 * @category Query
 * @package TrustedDevice\Application\UseCase\Query\CheckDeviceTrusted
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CheckDeviceTrustedQuery
{
  public function __construct(
    public string $token,
    public string $userId,
  ) {
  }
}
