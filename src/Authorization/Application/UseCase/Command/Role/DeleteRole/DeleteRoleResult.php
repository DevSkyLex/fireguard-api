<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Role\DeleteRole;

use Shared\Application\Message\ResultMessage;

/**
 * Result DeleteRoleResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteRoleResult implements ResultMessage
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
