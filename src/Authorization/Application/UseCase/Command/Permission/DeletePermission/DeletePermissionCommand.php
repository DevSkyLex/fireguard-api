<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Permission\DeletePermission;

use Shared\Application\Message\CommandMessage;

/**
 * Command DeletePermissionCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeletePermissionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $permissionId the permission ID
   */
  public function __construct(
    public string $permissionId,
  ) {
  }
  // #endregion
}
