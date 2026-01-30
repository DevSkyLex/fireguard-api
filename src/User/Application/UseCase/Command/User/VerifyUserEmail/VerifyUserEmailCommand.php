<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\User\VerifyUserEmail;

use Shared\Application\Message\CommandMessage;

/**
 * Command VerifyUserEmailCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class VerifyUserEmailCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the user ID
   */
  public function __construct(
    public readonly string $id,
  ) {
  }
  // #endregion
}
