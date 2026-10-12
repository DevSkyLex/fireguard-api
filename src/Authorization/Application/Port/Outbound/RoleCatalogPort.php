<?php

declare(strict_types=1);

namespace Authorization\Application\Port\Outbound;

use Authorization\Application\Contract\Catalog\RoleDefinition;

/**
 * Interface RoleCatalogPort
 *
 * Exposes bootstrap policy without restricting custom role resources.
 *
 * @category Port
 */
interface RoleCatalogPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * Lists bootstrap roles in deterministic seed order.
   *
   * @access public
   *
   * @return list<RoleDefinition> bootstrap definitions
   */
  public function definitions(): array;

  /**
   * Method defaultRoleName
   *
   * Returns the single default, or null when no default was registered.
   *
   * @access public
   *
   * @return string|null default role key
   */
  public function defaultRoleName(): ?string;
  // #endregion
}
