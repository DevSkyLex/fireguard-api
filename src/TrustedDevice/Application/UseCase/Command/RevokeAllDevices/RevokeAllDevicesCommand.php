<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\RevokeAllDevices;

use Shared\Application\Message\CommandMessage;

/**
 * Command RevokeAllDevicesCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeAllDevicesCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the command with
   * the user ID.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   */
  public function __construct(
    public readonly string $userId,
  ) {
  }
  // #endregion
}
