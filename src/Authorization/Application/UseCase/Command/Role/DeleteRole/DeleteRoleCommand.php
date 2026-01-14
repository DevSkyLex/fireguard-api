<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Role\DeleteRole;

use Shared\Application\Message\CommandMessage;

/**
 * Command DeleteRoleCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteRoleCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $roleId the role ID
   */
  public function __construct(
    public string $roleId,
  ) {
  }
  // #endregion
}
