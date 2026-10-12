<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Authorization;

use InvalidArgumentException;

use function str_ends_with;

/**
 * OrganizationPermissionCatalog.
 *
 * Central list of organization-scoped permission definitions.
 * These permissions are system-defined and cannot be created by users.
 *
 * @category Catalog
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationPermissionCatalog
{
  /**
   * The one write permission a suspended organization keeps.
   *
   * Suspension is read-only, but restoring is itself a write. Without this
   * escape hatch a suspended organization would wall itself in: `RestoreOrganization`
   * requires exactly this permission and there is no platform-level bypass.
   *
   * @since 1.2.0
   */
  private const string SUSPENSION_ESCAPE_HATCH = 'organization.settings.write';

  /**
   * Method isRead.
   *
   * Tells whether a permission only reads, and therefore survives any
   * non-active organization status.
   *
   * The rule is the `.read` suffix rather than an enumerated list, so a
   * permission added later is classified correctly without touching this
   * method. Two non-mutating permissions are nevertheless excluded on purpose:
   * `organization.compliance.export` and `organization.assistant.use` spend
   * resources on behalf of an organization whose access has been withdrawn.
   *
   * @since 1.2.0
   *
   * @param string $name the permission name
   *
   * @return bool true when the permission is a pure read
   */
  public static function isRead(string $name): bool
  {
    return str_ends_with($name, '.read');
  }

  /**
   * Method isSuspensionEscapeHatch.
   *
   * Tells whether a permission is the one write a suspended organization keeps,
   * so that it can be restored from inside. Archived organizations do not get
   * this hatch — see MODULE.md.
   *
   * @since 1.2.0
   *
   * @param string $name the permission name
   *
   * @return bool true for the restore escape hatch
   */
  public static function isSuspensionEscapeHatch(string $name): bool
  {
    return self::SUSPENSION_ESCAPE_HATCH === $name;
  }

  /**
   * Method isArchivalGuardedDownstream.
   *
   * Tells whether a permission gates operations that already refuse an
   * archived organization from their own handler, with a 409 naming the
   * archived state.
   *
   * Those permissions are let through the archived read-only rule so the
   * specific answer survives. Denying them here would flatten five documented
   * 409s — suspend, update settings, remove logo, transfer ownership,
   * reactivate member — into a bare 403, which tells the caller less. The
   * authorization layer defers where a more precise answer already exists.
   *
   * This applies to `ARCHIVED` only. Suspension has no such handler guards, so
   * nothing there would be shadowed.
   *
   * @since 1.2.0
   *
   * @param string $name the permission name
   *
   * @return bool true when the archived state is already answered downstream
   */
  public static function isArchivalGuardedDownstream(string $name): bool
  {
    return self::SUSPENSION_ESCAPE_HATCH === $name || 'organization.members.manage' === $name;
  }

  /**
   * Method dashboardReadDependencies.
   *
   * Returns the full permission set required to access the organization dashboard.
   *
   * @since 1.0.0
   *
   * @return list<string>
   */
  public static function dashboardReadDependencies(): array
  {
    return [
      'organization.dashboard.read',
      'organization.members.read',
      'organization.roles.read',
      'organization.facilities.read',
      'organization.equipment.read',
      'organization.inspection.read',
    ];
  }

  /**
   * Method dashboardTrendReadDependencies.
   *
   * Returns the permission set required to access a chart-level dashboard trend.
   *
   * @since 1.0.0
   *
   * @return list<string>
   */
  public static function dashboardTrendReadDependencies(string $metric): array
  {
    return match ($metric) {
      'inspections_performed',
      'non_conformities_opened',
      'non_conformities_resolved' => ['organization.inspection.read'],
      'equipment_created' => ['organization.equipment.read'],
      'facilities_created' => ['organization.facilities.read'],
      default => throw new InvalidArgumentException('Unsupported organization dashboard trend metric.'),
    };
  }

  /**
   * Method complianceReadDependencies.
   *
   * Returns the full permission set required to access the compliance
   * register (summary and single-facility detail), mirroring
   * `dashboardReadDependencies()`: the register aggregates facilities,
   * equipment, inspection non-conformities, and maintenance due-status, so
   * reading it requires the underlying read permission for each.
   *
   * @since 1.0.0
   *
   * @return list<string>
   */
  public static function complianceReadDependencies(): array
  {
    return [
      'organization.compliance.read',
      'organization.facilities.read',
      'organization.equipment.read',
      'organization.inspection.read',
      'organization.maintenance.read',
    ];
  }
}
