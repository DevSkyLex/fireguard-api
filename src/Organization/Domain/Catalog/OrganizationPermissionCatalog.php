<?php

declare(strict_types=1);

namespace Organization\Domain\Catalog;

use InvalidArgumentException;

use function str_ends_with;

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
      ['name' => 'organization.settings.write', 'description' => 'Manage organization settings (general, notifications, regional)'],
      ['name' => 'organization.delete', 'description' => 'Delete the organization'],

      // Member management
      ['name' => 'organization.members.read', 'description' => 'View organization members'],
      ['name' => 'organization.members.manage', 'description' => 'Manage organization members (add, invite, revoke)'],

      // Role management
      ['name' => 'organization.roles.read', 'description' => 'View organization roles'],
      ['name' => 'organization.roles.manage', 'description' => 'Manage organization roles (create, update, assign)'],

      // Team management
      ['name' => 'organization.teams.read', 'description' => 'View organization teams and their members'],
      ['name' => 'organization.teams.write', 'description' => 'Manage organization teams (create, update, add/remove members)'],
      ['name' => 'organization.teams.manage', 'description' => 'Delete organization teams'],

      // Facility management
      ['name' => 'organization.facilities.read', 'description' => 'View organization facilities'],
      ['name' => 'organization.facilities.write', 'description' => 'Manage organization facilities (create, update, archive, move, attachments)'],

      // Equipment management
      ['name' => 'organization.equipment.read', 'description' => 'View organization equipment and equipment dashboard statistics'],
      ['name' => 'organization.equipment.write', 'description' => 'Manage organization equipment, assignments, lifecycle, tags, and attachments'],

      // Inspection management
      ['name' => 'organization.inspection.read', 'description' => 'View organization inspections, checklists, non-conformities, and inspection dashboard statistics'],
      ['name' => 'organization.inspection.write', 'description' => 'Manage organization inspections, checklists, non-conformities, and attachments/photos'],

      // Field intervention management
      ['name' => 'organization.interventions.read', 'description' => 'View organization field interventions, validation issues, and attachments'],
      ['name' => 'organization.interventions.write', 'description' => 'Create and update organization field interventions'],
      ['name' => 'organization.interventions.plan', 'description' => 'Prepare and assign organization field interventions (including attachments while in draft)'],
      ['name' => 'organization.interventions.execute', 'description' => 'Execute assigned organization field intervention work (including attachments)'],
      ['name' => 'organization.interventions.review', 'description' => 'Review submitted organization field interventions'],
      ['name' => 'organization.interventions.publish', 'description' => 'Publish organization field interventions'],

      // Preventive maintenance management
      ['name' => 'organization.maintenance.read', 'description' => 'View organization preventive-maintenance schedules'],
      ['name' => 'organization.maintenance.manage', 'description' => 'Manage preventive-maintenance schedule overrides and generate inspection campaigns'],

      // Messaging
      ['name' => 'organization.messaging.read', 'description' => 'View organization conversations and messages'],
      ['name' => 'organization.messaging.write', 'description' => 'Post, edit, and delete own messages in organization conversations'],
      ['name' => 'organization.messaging.manage', 'description' => 'Archive conversations and moderate (delete) other members\' messages'],

      // Compliance register + regulatory export
      ['name' => 'organization.compliance.read', 'description' => 'View the compliance register/summary (organization rollup and per-facility breakdown)'],
      ['name' => 'organization.compliance.export', 'description' => 'Export the regulatory safety register PDF (also requires a pro/max plan)'],

      // Organization-scoped audit read (admin-granted: the activity feed
      // exposes who did what across the whole organization, so this is
      // deliberately not part of the member system role — admins hold it
      // through the organization.* wildcard and may grant it explicitly)
      ['name' => 'organization.audit.read', 'description' => 'View the organization activity feed (audit events scoped to the organization)'],

      // Four-eyes approval workflows
      ['name' => 'organization.approvals.read', 'description' => 'View the organization\'s pending and decided four-eyes approval requests'],
      ['name' => 'organization.approvals.request', 'description' => 'Initiate a regulated action gated behind approval (enforced only when the organization requires it)'],
      ['name' => 'organization.approvals.decide', 'description' => 'Approve or reject four-eyes approval requests'],

      // Outbound webhooks (admin/integration only — not part of the member system role)
      ['name' => 'organization.webhooks.read', 'description' => 'View organization webhook subscriptions and their delivery logs'],
      ['name' => 'organization.webhooks.manage', 'description' => 'Manage organization webhook subscriptions (create, update, delete, rotate secret, test, redeliver)'],

      // AI assistant (admin-granted: a conversation transcript leaves the
      // application boundary for the inference backend, so this is deliberately
      // not part of the member system role — the organization opts in twice,
      // once via `settings.assistant.enabled` and once by granting this)
      ['name' => 'organization.assistant.use', 'description' => 'Ask the organization AI assistant and read its threads'],

      // Calendar events
      ['name' => 'organization.events.read', 'description' => 'View the organization calendar feed and its standalone events'],
      ['name' => 'organization.events.write', 'description' => 'Create, update, and delete standalone organization calendar events'],

      // Wildcard
      ['name' => 'organization.*', 'description' => 'Full access to all organization operations (owner)'],
    ];
  }
}
