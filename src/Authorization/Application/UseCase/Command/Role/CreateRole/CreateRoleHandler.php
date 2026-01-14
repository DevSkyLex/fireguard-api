<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Role\CreateRole;

use Authorization\Application\Port\Outbound\{PermissionRepositoryPort, RoleRepositoryPort};
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, RoleId, RoleName};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;

use function array_map;
use function array_values;

/**
 * Handler CreateRoleHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateRoleHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param RoleRepositoryPort $roleRepository the role repository
   * @param PermissionRepositoryPort $permissionRepository the permission repository
   * @param UuidFactory $uuidFactory the UUID factory
   */
  public function __construct(
    private RoleRepositoryPort $roleRepository,
    private PermissionRepositoryPort $permissionRepository,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the CreateRoleCommand.
   *
   * @since 1.0.0
   *
   * @param CreateRoleCommand $command the command
   *
   * @return CreateRoleResult the result
   */
  public function __invoke(CreateRoleCommand $command): CreateRoleResult
  {
    $permissions = [];
    foreach ($command->permissionIds as $permissionId) {
      $permission = $this->permissionRepository->findById(
        id: new PermissionId(value: $permissionId),
      );
      if (null !== $permission) {
        $permissions[] = $permission;
      }
    }

    $role = Role::create(
      id: $this->uuidFactory->create(RoleId::class),
      name: new RoleName(value: $command->name),
      description: $command->description ?? '',
      isSystem: $command->isSystem,
      tenantId: null,
    );

    foreach ($permissions as $permission) {
      $role->addPermission(permission: $permission);
    }

    $this->roleRepository->save(role: $role);

    return new CreateRoleResult(
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
