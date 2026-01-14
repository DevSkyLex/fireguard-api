<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Role\UpdateRole;

use Shared\Application\Message\CommandMessage;

/**
 * Command UpdateRoleCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateRoleCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $roleId the role ID
   * @param string $name the role name (empty to keep current)
   * @param string|null $description the role description
   * @param list<string> $permissionIds the permission IDs to assign
   */
  public function __construct(
    public string $roleId,
    public string $name = '',
    public ?string $description = null,
    public array $permissionIds = [],
  ) {
  }
  // #endregion
}
