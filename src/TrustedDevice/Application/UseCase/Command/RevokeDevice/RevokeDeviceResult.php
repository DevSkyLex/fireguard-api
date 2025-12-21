<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\RevokeDevice;

use Shared\Application\Message\ResultMessage;

/**
 * Result RevokeDeviceResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeDeviceResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the result with the
   * success status and device ID.
   *
   * @since 1.0.0
   *
   * @param bool $success whether the revocation was successful
   * @param string $deviceId the revoked device ID
   */
  public function __construct(
    public readonly bool $success,
    public readonly string $deviceId,
  ) {
  }
  // #endregion
}
