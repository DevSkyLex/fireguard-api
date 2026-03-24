<?php

declare(strict_types=1);

namespace Organization\Domain\Catalog;

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

      // Member management
      ['name' => 'organization.members.read', 'description' => 'View organization members'],
      ['name' => 'organization.members.manage', 'description' => 'Manage organization members (add, invite, revoke)'],

      // Role management
      ['name' => 'organization.roles.read', 'description' => 'View organization roles'],
      ['name' => 'organization.roles.manage', 'description' => 'Manage organization roles (create, update, assign)'],

      // Facility management
      ['name' => 'organization.facilities.read', 'description' => 'View organization facilities'],
      ['name' => 'organization.facilities.write', 'description' => 'Manage organization facilities (create, update, archive, move)'],

      // Legal profile
      ['name' => 'organization.legal_profile.write', 'description' => 'Manage organization legal profile'],

      // Wildcard
      ['name' => 'organization.*', 'description' => 'Full access to all organization operations (owner)'],
    ];
  }
}
