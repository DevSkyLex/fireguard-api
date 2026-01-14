<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Query\Permission\GetPermission;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetPermissionQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPermissionQuery implements QueryMessage
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
