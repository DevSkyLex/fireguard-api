<?php

declare(strict_types=1);

namespace Organization\Domain\Catalog;

use function array_unique;
use function array_values;

/**
 * OrganizationSystemRoleCatalog.
 *
 * Central list of system-managed organization role definitions.
 *
 * @category Catalog
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSystemRoleCatalog
{
  public const string ADMIN = 'admin';

  public const string MEMBER = 'member';

  /**
   * Method permissionsFor.
   *
   * Returns canonical permissions for a system role name.
   *
   * @since 1.0.0
   *
   * @param string $roleName the role name
   *
   * @return list<string> the canonical permissions
   */
  public static function permissionsFor(string $roleName): array
  {
    return match ($roleName) {
      self::ADMIN => ['organization.*'],
      self::MEMBER => [
        'organization.read',
        'organization.dashboard.read',
        'organization.members.read',
        'organization.roles.read',
        'organization.facilities.read',
        'organization.equipment.read',
        'organization.inspection.read',
        'organization.interventions.read',
        'organization.maintenance.read',
        'organization.teams.read',
        'organization.messaging.read',
        'organization.compliance.read',
        'organization.approvals.read',
        'organization.approvals.request',
        'organization.events.read',
      ],
      default => [],
    };
  }

  /**
   * Method mergePermissions.
   *
   * Merges persisted permissions with canonical system role permissions.
   *
   * @since 1.0.0
   *
   * @param string $roleName the role name
   * @param list<string> $permissions the persisted permissions
   * @param bool $isSystem whether the role is system-managed
   *
   * @return list<string> the merged permissions
   */
  public static function mergePermissions(string $roleName, array $permissions, bool $isSystem): array
  {
    if (!$isSystem) {
      return $permissions;
    }

    $canonicalPermissions = self::permissionsFor($roleName);
    if ([] === $canonicalPermissions) {
      return $permissions;
    }

    return array_values(array_unique([
      ...$permissions,
      ...$canonicalPermissions,
    ]));
  }
}
