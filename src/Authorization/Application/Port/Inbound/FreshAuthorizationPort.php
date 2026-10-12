<?php

declare(strict_types=1);

namespace Authorization\Application\Port\Inbound;

/**
 * Interface FreshAuthorizationPort
 *
 * Rechecks required platform grants directly against current auth-side assignments without shared caches or identity maps.
 *
 * @category Port
 */
interface FreshAuthorizationPort
{
  // #region Methods
  /**
   * Method hasPermission
   *
   * @access public
   *
   * @param string $userId current account identifier
   * @param string $permission exact registered required permission
   * @param string|null $tenantId current caller tenant context
   *
   * @return bool whether a current scoped grant matches
   */
  public function hasPermission(string $userId, string $permission, ?string $tenantId): bool;

  /**
   * Method hasRole
   *
   * @access public
   *
   * @param string $userId current account identifier
   * @param string $roleName actual required role key
   * @param string|null $tenantId current caller tenant context
   *
   * @return bool whether the scoped assignment currently exists
   */
  public function hasRole(string $userId, string $roleName, ?string $tenantId): bool;

  /**
   * Method roleNames
   *
   * @access public
   *
   * @param string $userId current account identifier
   * @param string|null $tenantId current tenant context
   *
   * @return list<string> current owner role keys before native role hierarchy expansion
   */
  public function roleNames(string $userId, ?string $tenantId): array;
  // #endregion
}
