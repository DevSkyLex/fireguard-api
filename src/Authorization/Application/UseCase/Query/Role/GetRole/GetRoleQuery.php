<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Query\Role\GetRole;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetRoleQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetRoleQuery implements QueryMessage
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
