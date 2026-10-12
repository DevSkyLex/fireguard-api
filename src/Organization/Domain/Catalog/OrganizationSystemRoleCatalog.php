<?php

declare(strict_types=1);

namespace Organization\Domain\Catalog;

/**
 * OrganizationSystemRoleCatalog.
 *
 * Stable compatibility names for default roles; canonical grants come from the Application catalog port.
 *
 * @category Catalog
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSystemRoleCatalog
{
  /**
   * Constant ADMIN
   */
  public const string ADMIN = 'admin';

  /**
   * Constant MEMBER
   */
  public const string MEMBER = 'member';
}
