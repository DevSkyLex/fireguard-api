<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Permission\CreatePermission;

use Shared\Application\Message\CommandMessage;

/**
 * Command CreatePermissionCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreatePermissionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $name the permission name
   * @param string|null $description the permission description
   */
  public function __construct(
    public string $name,
    public ?string $description = null,
  ) {
  }
  // #endregion
}
