<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Query\Role\GetRole;

use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Shared\Application\Message\ResultMessage;

/**
 * Result GetRoleResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetRoleResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the role ID
   * @param string $name the role name
   * @param string|null $description the role description
   * @param bool $isSystem whether this is a system role
   * @param string $createdAt the creation timestamp
   * @param list<GetPermissionResult> $permissions the assigned permissions
   */
  public function __construct(
    public string $id,
    public string $name,
    public ?string $description,
    public bool $isSystem,
    public string $createdAt,
    public array $permissions,
  ) {
  }
  // #endregion
}
