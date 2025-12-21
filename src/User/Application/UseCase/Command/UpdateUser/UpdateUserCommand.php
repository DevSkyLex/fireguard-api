<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\UpdateUser;

use Shared\Application\Message\CommandMessage;

/**
 * Command UpdateUserCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateUserCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the command with the user ID
   * and optional profile data.
   *
   * @since 1.0.0
   *
   * @param string      $id        the user ID
   * @param string|null $firstName the first name
   * @param string|null $lastName  the last name
   * @param string|null $avatarUrl the avatar URL
   */
  public function __construct(
    public readonly string $id,
    public readonly ?string $firstName = null,
    public readonly ?string $lastName = null,
    public readonly ?string $avatarUrl = null,
  ) {
  }
  // #endregion
}
