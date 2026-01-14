<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Query\Permission\ListPermissions;

use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Domain\Model\Permission\Permission;
use Shared\Application\Message\QueryHandler;

use function array_map;
use function array_values;

/**
 * Handler ListPermissionsHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListPermissionsHandler implements QueryHandler
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
   * Handles the ListPermissionsQuery.
   *
   * @since 1.0.0
   *
   * @param ListPermissionsQuery $query the query
   *
   * @return ListPermissionsResult the result
   */
  public function __invoke(ListPermissionsQuery $query): ListPermissionsResult
  {
    $permissions = $this->permissionRepository->findAll();

    return new ListPermissionsResult(
      permissions: array_values(array_map(
        fn (Permission $permission) => new GetPermissionResult(
          id: $permission->id()->value,
          name: $permission->name()->value,
          description: $permission->description(),
          createdAt: $permission->createdAt()->format('Y-m-d H:i:s'),
        ),
        $permissions,
      )),
    );
  }
  // #endregion
}
