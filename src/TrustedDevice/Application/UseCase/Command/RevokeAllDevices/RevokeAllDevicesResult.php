<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\RevokeAllDevices;

use Shared\Application\Message\ResultMessage;

/**
 * Result RevokeAllDevicesResult
 * @final
 *
 * Result of revoking all trusted devices for a user.
 *
 * @category Result
 * @package TrustedDevice\Application\UseCase\Command\RevokeAllDevices
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeAllDevicesResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the result with the number 
   * of devices revoked.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $revokedCount Number of devices revoked.
   */
  public function __construct(
    public readonly int $revokedCount,
  ) {}
  //#endregion
}
