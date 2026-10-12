<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

/**
 * Interface OrganizationSystemRoleCatalogPort
 *
 * Resolves canonical grants at creation and read time without rewriting persisted role history.
 *
 * @category Port
 */
interface OrganizationSystemRoleCatalogPort
{
  // #region Methods
  /**
   * Method permissionsFor
   *
   * @access public
   *
   * @return list<string> canonical registered grants, empty for an unknown role
   */
  public function permissionsFor(string $roleName): array;

  /**
   * Method mergePermissions
   *
   * @access public
   *
   * @param list<string> $permissions historical persisted grants
   *
   * @return list<string> grants plus current contributions for system roles
   */
  public function mergePermissions(string $roleName, array $permissions, bool $isSystem): array;
  // #endregion
}
