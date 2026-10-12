<?php

declare(strict_types=1);

namespace Authorization\Application\Port\Outbound;

use Authorization\Application\Contract\Catalog\PermissionDefinition;

/**
 * Interface PermissionCatalogPort
 *
 * Exposes registered vocabulary; it neither grants access nor governs historical hydration.
 *
 * @category Port
 */
interface PermissionCatalogPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * Lists definitions in deterministic publication order.
   *
   * @access public
   *
   * @return list<PermissionDefinition> registered vocabulary
   */
  public function definitions(): array;

  /**
   * Method contains
   *
   * Tests exact registration, including wildcard keys, without wildcard expansion.
   *
   * @access public
   *
   * @param string $name permission key
   *
   * @return bool whether registered
   */
  public function contains(string $name): bool;
  // #endregion
}
