<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\RevokeDevice;

use Shared\Application\Message\CommandMessage;

/**
 * Command RevokeDeviceCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeDeviceCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the command with the
   * device ID and user ID.
   *
   * @since 1.0.0
   *
   * @param string $deviceId the device ID
   * @param string $userId   the user ID
   */
  public function __construct(
    public readonly string $deviceId,
    public readonly string $userId,
  ) {
  }
  // #endregion
}
