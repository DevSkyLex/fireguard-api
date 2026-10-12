<?php

declare(strict_types=1);

namespace Authorization\Application\Port\Inbound;

use Authorization\Application\Contract\Catalog\PermissionDefinition;

/**
 * Interface PermissionCatalogContributionPort
 *
 * Owners publish deterministic pure definitions with no I/O or service resolution.
 *
 * @category Port
 */
interface PermissionCatalogContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * Returns definitions for runtime composition and compile-time validation.
   *
   * @access public
   *
   * @return list<PermissionDefinition> owner contributions
   */
  public static function definitions(): array;
  // #endregion
}
