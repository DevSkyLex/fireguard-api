<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Role\RemovePermissionFromRole;

use Authorization\Application\Port\Outbound\{PermissionRepositoryPort, RoleRepositoryPort};
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Domain\Exception\{PermissionNotFoundException, RoleNotFoundException};
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, RoleId};
use Shared\Application\Message\CommandHandler;

use function array_map;
use function array_values;

/**
 * Handler RemovePermissionFromRoleHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemovePermissionFromRoleHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param RoleRepositoryPort $roleRepository the role repository
   * @param PermissionRepositoryPort $permissionRepository the permission repository
   */
  public function __construct(
    private RoleRepositoryPort $roleRepository,
    private PermissionRepositoryPort $permissionRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the RemovePermissionFromRoleCommand.
   *
   * @since 1.0.0
   *
   * @param RemovePermissionFromRoleCommand $command the command
   *
   * @return RemovePermissionFromRoleResult the result
   */
  public function __invoke(RemovePermissionFromRoleCommand $command): RemovePermissionFromRoleResult
  {
    $role = $this->roleRepository->findById(
      id: new RoleId(value: $command->roleId),
    );

    if (null === $role) {
      throw RoleNotFoundException::withId(roleId: $command->roleId);
    }

    $permission = $this->permissionRepository->findById(
      id: new PermissionId(value: $command->permissionId),
    );

    if (null === $permission) {
      throw PermissionNotFoundException::withId(permissionId: $command->permissionId);
    }

    $role->removePermission(permission: $permission);

    $this->roleRepository->save(role: $role);

    return new RemovePermissionFromRoleResult(
      id: $role->id()->value,
      name: $role->name()->value,
      description: $role->description(),
      isSystem: $role->isSystem(),
      createdAt: $role->createdAt()->format('Y-m-d H:i:s'),
      permissions: array_values(array_map(
        fn (Permission $permission) => $this->mapPermission($permission),
        $role->permissions(),
      )),
    );
  }

  /**
   * Method mapPermission.
   *
   * Maps a Permission to a result object.
   *
   * @param Permission $permission the permission
   *
   * @return GetPermissionResult the permission result
   */
  private function mapPermission(Permission $permission): GetPermissionResult
  {
    return new GetPermissionResult(
      id: $permission->id()->value,
      name: $permission->name()->value,
      description: $permission->description(),
      createdAt: $permission->createdAt()->format('Y-m-d H:i:s'),
    );
  }
  // #endregion
}
