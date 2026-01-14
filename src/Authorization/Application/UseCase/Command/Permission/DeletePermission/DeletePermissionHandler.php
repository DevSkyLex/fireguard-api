<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Permission\DeletePermission;

use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Domain\Exception\PermissionNotFoundException;
use Authorization\Domain\ValueObject\PermissionId;
use Shared\Application\Message\CommandHandler;

/**
 * Handler DeletePermissionHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeletePermissionHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param PermissionRepositoryPort $permissionRepository the permission repository
   */
  public function __construct(
    private PermissionRepositoryPort $permissionRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the DeletePermissionCommand.
   *
   * @since 1.0.0
   *
   * @param DeletePermissionCommand $command the command
   *
   * @return DeletePermissionResult the result
   */
  public function __invoke(DeletePermissionCommand $command): DeletePermissionResult
  {
    $permission = $this->permissionRepository->findById(
      id: new PermissionId(value: $command->permissionId),
    );

    if (null === $permission) {
      throw PermissionNotFoundException::withId(permissionId: $command->permissionId);
    }

    $this->permissionRepository->delete(permission: $permission);

    return new DeletePermissionResult(permissionId: $command->permissionId);
  }
  // #endregion
}
