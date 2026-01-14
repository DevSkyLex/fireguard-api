<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Query\Permission\ListPermissions;

use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Shared\Application\Message\ResultMessage;

/**
 * Result ListPermissionsResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListPermissionsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<GetPermissionResult> $permissions the list of permissions
   */
  public function __construct(
    public array $permissions,
  ) {
  }
  // #endregion
}
