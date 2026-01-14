<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Role\DeleteRole;

use Authorization\Application\Port\Outbound\RoleRepositoryPort;
use Authorization\Domain\Exception\{RoleNotFoundException, SystemRoleDeletionException};
use Authorization\Domain\ValueObject\RoleId;
use Shared\Application\Message\CommandHandler;

/**
 * Handler DeleteRoleHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteRoleHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param RoleRepositoryPort $roleRepository the role repository
   */
  public function __construct(
    private RoleRepositoryPort $roleRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the DeleteRoleCommand.
   *
   * @since 1.0.0
   *
   * @param DeleteRoleCommand $command the command
   *
   * @return DeleteRoleResult the result
   */
  public function __invoke(DeleteRoleCommand $command): DeleteRoleResult
  {
    $role = $this->roleRepository->findById(
      id: new RoleId(value: $command->roleId),
    );

    if (null === $role) {
      throw RoleNotFoundException::withId(roleId: $command->roleId);
    }

    if ($role->isSystem()) {
      throw SystemRoleDeletionException::forRole(roleId: $command->roleId);
    }

    $this->roleRepository->delete(role: $role);

    return new DeleteRoleResult(roleId: $command->roleId);
  }
  // #endregion
}
