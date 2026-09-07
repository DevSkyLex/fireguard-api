<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\EmailOwnership\ConfirmEmailOwnership;

use Shared\Application\Message\CommandMessage;

/**
 * Command ConfirmEmailOwnershipCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmEmailOwnershipCommand implements CommandMessage
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param string $userId authenticated account identifier
   * @param string $challengeToken single-use challenge token
   * @param string $code submitted email code
   */
  public function __construct(public string $userId, public string $challengeToken, public string $code)
  {
  }
  // #endregion
}
