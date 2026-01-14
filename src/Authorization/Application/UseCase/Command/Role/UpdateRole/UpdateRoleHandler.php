<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Role\UpdateRole;

use Authorization\Application\Port\Outbound\{PermissionRepositoryPort, RoleRepositoryPort};
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Domain\Exception\RoleNotFoundException;
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, RoleId, RoleName};
use Shared\Application\Message\CommandHandler;

use function array_map;
use function array_values;

/**
 * Handler UpdateRoleHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateRoleHandler implements CommandHandler
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
   * Handles the UpdateRoleCommand.
   *
   * @since 1.0.0
   *
   * @param UpdateRoleCommand $command the command
   *
   * @return UpdateRoleResult the result
   */
  public function __invoke(UpdateRoleCommand $command): UpdateRoleResult
  {
    $role = $this->roleRepository->findById(
      id: new RoleId(value: $command->roleId),
    );

    if (null === $role) {
      throw RoleNotFoundException::withId(roleId: $command->roleId);
    }

    $role->update(
      name: new RoleName(value: '' !== $command->name ? $command->name : $role->name()->value),
      description: $command->description ?? $role->description(),
    );

    if (!empty($command->permissionIds)) {
      foreach ($role->permissions() as $existingPermission) {
        $role->removePermission(permission: $existingPermission);
      }

      foreach ($command->permissionIds as $permissionId) {
        $permission = $this->permissionRepository->findById(
          id: new PermissionId(value: $permissionId),
        );
        if (null !== $permission) {
          $role->addPermission(permission: $permission);
        }
      }
    }

    $this->roleRepository->save(role: $role);

    return new UpdateRoleResult(
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
