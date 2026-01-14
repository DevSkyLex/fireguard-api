<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Role\CreateRole;

use Shared\Application\Message\CommandMessage;

/**
 * Command CreateRoleCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateRoleCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $name the role name
   * @param string|null $description the role description
   * @param bool $isSystem whether this is a system role
   * @param list<string> $permissionIds the permission IDs to assign
   */
  public function __construct(
    public string $name,
    public ?string $description = null,
    public bool $isSystem = false,
    public array $permissionIds = [],
  ) {
  }
  // #endregion
}
