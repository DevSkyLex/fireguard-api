<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Role\AddPermissionToRole;

use Shared\Application\Message\CommandMessage;

/**
 * Command AddPermissionToRoleCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddPermissionToRoleCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $roleId the role ID
   * @param string $permissionId the permission ID
   */
  public function __construct(
    public string $roleId,
    public string $permissionId,
  ) {
  }
  // #endregion
}
