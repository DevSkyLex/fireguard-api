<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Query\Role\ListRoles;

use Authorization\Application\Port\Outbound\RoleRepositoryPort;
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Application\UseCase\Query\Role\GetRole\GetRoleResult;
use Authorization\Domain\Model\Permission\Permission;
use Shared\Application\Message\QueryHandler;

use function array_map;
use function array_values;

/**
 * Handler ListRolesHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListRolesHandler implements QueryHandler
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
   * Handles the ListRolesQuery.
   *
   * @since 1.0.0
   *
   * @param ListRolesQuery $query the query
   *
   * @return ListRolesResult the result
   */
  public function __invoke(ListRolesQuery $query): ListRolesResult
  {
    $roles = $this->roleRepository->findAll(isSystem: $query->isSystem);

    return new ListRolesResult(
      roles: array_values(array_map(
        fn ($role) => new GetRoleResult(
          id: $role->id()->value,
          name: $role->name()->value,
          description: $role->description(),
          isSystem: $role->isSystem(),
          createdAt: $role->createdAt()->format('Y-m-d H:i:s'),
          permissions: array_values(array_map(
            fn (Permission $permission) => $this->mapPermission($permission),
            $role->permissions(),
          )),
        ),
        $roles,
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
