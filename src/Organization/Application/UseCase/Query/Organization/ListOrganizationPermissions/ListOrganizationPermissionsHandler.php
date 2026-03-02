<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationPermissions;

use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListOrganizationPermissionsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationPermissionsHandler implements QueryHandler
{
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

    foreach (OrganizationPermissionCatalog::definitions() as $definition) {
      $results[] = new GetOrganizationPermissionResult(
        name: $definition['name'],
        description: $definition['description'],
      );
    }

    return new ListOrganizationPermissionsResult($results);
  }
  // #endregion
}
