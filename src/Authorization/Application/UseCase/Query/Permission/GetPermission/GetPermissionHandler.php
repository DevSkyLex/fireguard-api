<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Query\Permission\GetPermission;

use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Domain\Exception\PermissionNotFoundException;
use Authorization\Domain\ValueObject\PermissionId;
use Shared\Application\Message\QueryHandler;

/**
 * Handler GetPermissionHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPermissionHandler implements QueryHandler
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
   * Handles the GetPermissionQuery.
   *
   * @since 1.0.0
   *
   * @param GetPermissionQuery $query the query
   *
   * @return GetPermissionResult the result
   */
  public function __invoke(GetPermissionQuery $query): GetPermissionResult
  {
    $permission = $this->permissionRepository->findById(
      id: new PermissionId(value: $query->permissionId),
    );

    if (null === $permission) {
      throw PermissionNotFoundException::withId(permissionId: $query->permissionId);
    }

    return new GetPermissionResult(
      id: $permission->id()->value,
      name: $permission->name()->value,
      description: $permission->description(),
      createdAt: $permission->createdAt()->format('Y-m-d H:i:s'),
    );
  }
  // #endregion
}
