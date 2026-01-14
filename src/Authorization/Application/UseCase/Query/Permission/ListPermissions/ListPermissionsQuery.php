<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Query\Permission\ListPermissions;

use Shared\Application\Message\QueryMessage;

/**
 * Query ListPermissionsQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListPermissionsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   */
  public function __construct()
  {
  }
  // #endregion
}
