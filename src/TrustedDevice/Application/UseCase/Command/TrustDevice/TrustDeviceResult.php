<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\TrustDevice;

use Shared\Application\Message\ResultMessage;
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
final readonly class TrustDeviceResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the result with the 
   * device ID, token, device name, and 
   * expiration date.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $deviceId The device ID.
   * @param string $token The token.
   * @param string $deviceName The device name.
   * @param DateTimeImmutable $expiresAt The expiration date.
   */
  public function __construct(
    public readonly string $deviceId,
    public readonly string $token,
    public readonly string $deviceName,
    public readonly DateTimeImmutable $expiresAt,
  ) {}
  //#endregion
}
