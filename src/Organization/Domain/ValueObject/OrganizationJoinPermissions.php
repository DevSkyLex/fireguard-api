<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use function str_ends_with;
use function str_starts_with;
use function substr;

/**
 * ValueObject OrganizationJoinPermissions.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationJoinPermissions
{
  // #region Methods
  /**
   * Checks complete containment, including wildcard grants, without expanding an unknown permission catalog.
   *
   * @since 1.0.0
   *
   * @param list<string> $requested the proposed role permissions
   * @param list<string> $ceiling the current system member permissions
   *
   * @return bool whether every requested grant is contained
   */
  public static function isSubset(array $requested, array $ceiling): bool
  {
    foreach ($requested as $permission) {
      $contained = false;
      foreach ($ceiling as $allowed) {
        if ($allowed === $permission || ('*' === $allowed) || (str_ends_with($allowed, '.*') && str_starts_with($permission, substr($allowed, 0, -1)))) {
          $contained = true;

          break;
        }
      }
      if (!$contained) {
        return false;
      }
    }

    return true;
  }
  // #endregion
}
