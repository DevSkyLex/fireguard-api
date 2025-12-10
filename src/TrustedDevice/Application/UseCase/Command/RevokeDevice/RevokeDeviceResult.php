<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\RevokeDevice;

use Shared\Application\Message\ResultMessage;

/**
 * Result RevokeDeviceResult
 * @final
 *
 * Result of revoking a trusted device.
 *
 * @category Result
 * @package TrustedDevice\Application\UseCase\Command\RevokeDevice
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeDeviceResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the result with the 
   * success status and device ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param bool $success Whether the revocation was successful.
   * @param string $deviceId The revoked device ID.
   */
  public function __construct(
    public readonly bool $success,
    public readonly string $deviceId,
  ) {}
  //#endregion
}
