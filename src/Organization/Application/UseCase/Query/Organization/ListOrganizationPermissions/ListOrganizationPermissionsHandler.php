<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationPermissions;

use Organization\Application\Port\Inbound\OrganizationPermissionCatalogPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListOrganizationPermissionsHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationPermissionsHandler implements QueryHandler
{
  /**
   * Method __construct
   */
  public function __construct(private OrganizationPermissionCatalogPort $permissionCatalog)
  {
  }

  // #region Methods

  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param ListOrganizationPermissionsQuery $query the query payload
   */
  public function __invoke(ListOrganizationPermissionsQuery $query): ListOrganizationPermissionsResult
  {
    $results = [];

    foreach ($this->permissionCatalog->definitions() as $definition) {
      $results[] = new GetOrganizationPermissionResult(
        name: $definition->name,
        description: $definition->description,
      );
    }

    return new ListOrganizationPermissionsResult($results);
  }
  // #endregion
}
