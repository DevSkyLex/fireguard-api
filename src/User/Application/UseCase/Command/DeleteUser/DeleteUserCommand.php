<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\DeleteUser;

use Shared\Application\Message\CommandMessage;

/**
 * Command DeleteUserCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteUserCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * DeleteUserCommand class.
   *
   * @since 1.0.0
   *
   * @param string $id the user ID
   */
  public function __construct(
    public string $id,
  ) {
  }
  // #endregion
}
