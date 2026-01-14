<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Permission\UpdatePermission;

use Shared\Application\Message\CommandMessage;

/**
 * Command UpdatePermissionCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdatePermissionCommand implements CommandMessage
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
