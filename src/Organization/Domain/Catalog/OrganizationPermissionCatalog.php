<?php

declare(strict_types=1);

namespace Organization\Domain\Catalog;

use InvalidArgumentException;

/**
 * OrganizationPermissionCatalog.
 *
 * Central list of organization-scoped permission definitions.
 * These permissions are system-defined and cannot be created by users.
 *
 * @category Catalog
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationPermissionCatalog
{
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
      default => throw new InvalidArgumentException('Unsupported organization dashboard trend metric.'),
    };
  }

  /**
   * Method descriptionFor.
   *
   * Returns the description for a given permission name, or an empty string if unknown.
   *
   * @since 1.0.0
   *
   * @param string $name the permission name
   *
   * @return string the description
   */
  public static function descriptionFor(string $name): string
  {
    foreach (self::definitions() as $definition) {
      if ($definition['name'] === $name) {
        return $definition['description'];
      }
    }

    return '';
  }

  /**
   * Method definitions.
   *
   * Returns all available organization-scoped permissions.
   *
   * @since 1.0.0
   *
   * @return list<array{name: string, description: string}>
   */
  public static function definitions(): array
  {
    return [
      // Organization general
      ['name' => 'organization.read', 'description' => 'View organization details'],
      ['name' => 'organization.dashboard.read', 'description' => 'View organization dashboard analytics and KPIs'],

      // Member management
      ['name' => 'organization.members.read', 'description' => 'View organization members'],
      ['name' => 'organization.members.manage', 'description' => 'Manage organization members (add, invite, revoke)'],

      // Role management
      ['name' => 'organization.roles.read', 'description' => 'View organization roles'],
      ['name' => 'organization.roles.manage', 'description' => 'Manage organization roles (create, update, assign)'],

      // Facility management
      ['name' => 'organization.facilities.read', 'description' => 'View organization facilities'],
      ['name' => 'organization.facilities.write', 'description' => 'Manage organization facilities (create, update, archive, move)'],

      // Equipment management
      ['name' => 'organization.equipment.read', 'description' => 'View organization equipment and equipment dashboard statistics'],
      ['name' => 'organization.equipment.write', 'description' => 'Manage organization equipment, assignments, lifecycle, tags, and attachments'],

      // Inspection management
      ['name' => 'organization.inspection.read', 'description' => 'View organization inspections, checklists, non-conformities, and inspection dashboard statistics'],
      ['name' => 'organization.inspection.write', 'description' => 'Manage organization inspections, checklists, and non-conformities'],

      // Legal profile
      ['name' => 'organization.legal_profile.write', 'description' => 'Manage organization legal profile'],

      // Wildcard
      ['name' => 'organization.*', 'description' => 'Full access to all organization operations (owner)'],
    ];
  }
}
