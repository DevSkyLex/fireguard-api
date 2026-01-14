<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Permission\UpdatePermission;

use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Domain\Exception\PermissionNotFoundException;
use Authorization\Domain\ValueObject\PermissionId;
use Shared\Application\Message\CommandHandler;

/**
 * Handler UpdatePermissionHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdatePermissionHandler implements CommandHandler
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
   * Handles the UpdatePermissionCommand.
   *
   * @since 1.0.0
   *
   * @param UpdatePermissionCommand $command the command
   *
   * @return UpdatePermissionResult the result
   */
  public function __invoke(UpdatePermissionCommand $command): UpdatePermissionResult
  {
    $permission = $this->permissionRepository->findById(
      id: new PermissionId(value: $command->permissionId),
    );

    if (null === $permission) {
      throw PermissionNotFoundException::withId(permissionId: $command->permissionId);
    }

    return new UpdatePermissionResult(
      id: $permission->id()->value,
      name: $permission->name()->value,
      description: $permission->description(),
      createdAt: $permission->createdAt()->format('Y-m-d H:i:s'),
    );
  }
  // #endregion
}
