<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\EmailOwnership\StartEmailOwnership;

use Shared\Application\Message\CommandMessage;

/**
 * Command StartEmailOwnershipCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartEmailOwnershipCommand implements CommandMessage
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param string $userId authenticated account identifier
   */
  public function __construct(public string $userId)
  {
  }
  // #endregion
}
