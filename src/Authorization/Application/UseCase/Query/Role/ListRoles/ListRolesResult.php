<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Query\Role\ListRoles;

use Authorization\Application\UseCase\Query\Role\GetRole\GetRoleResult;
use Shared\Application\Message\ResultMessage;

/**
 * Result ListRolesResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListRolesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<GetRoleResult> $roles the list of roles
   */
  public function __construct(
    public array $roles,
  ) {
  }
  // #endregion
}
