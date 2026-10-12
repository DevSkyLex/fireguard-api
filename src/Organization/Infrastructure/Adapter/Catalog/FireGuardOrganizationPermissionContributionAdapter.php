<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Catalog;

use Organization\Application\Contract\Authorization\OrganizationPermissionDefinition;
use Organization\Application\Port\Outbound\OrganizationPermissionContributionPort;

/**
 * Class FireGuardOrganizationPermissionContributionAdapter
 *
 * Publishes the existing FireGuard permission vocabulary for this application composition.
 *
 * @category Adapter
 */
final readonly class FireGuardOrganizationPermissionContributionAdapter implements OrganizationPermissionContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationPermissionDefinition>
   */
  public static function definitions(): array
  {
    $definitions = [
      // Automation execution history and explicit recovery
      ['name' => 'organization.automation.read', 'description' => 'View automation rules and execution history'],
      ['name' => 'organization.automation.manage', 'description' => 'Retry failed automation actions'],

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

      // Internal customer management
      ['name' => 'organization.customers.read', 'description' => 'View internal customers and their operational contacts'],
      ['name' => 'organization.customers.manage', 'description' => 'Manage internal customers, contacts and archival'],

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
      ['name' => 'organization.workload.read', 'description' => 'View team workload in the organization'],
      ['name' => 'organization.workload.manage', 'description' => 'Manage organization and member working capacities'],
      ['name' => 'organization.interventions.time.write', 'description' => 'Record and correct own intervention time'],
      ['name' => 'organization.interventions.time.manage', 'description' => 'Record and correct intervention time for other members'],

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

      // Repair requests and internal maintenance resources
      ['name' => 'organization.service_requests.read', 'description' => 'View internal maintenance requests'],
      ['name' => 'organization.service_requests.create', 'description' => 'Declare internal maintenance requests'],
      ['name' => 'organization.service_requests.manage', 'description' => 'Qualify maintenance requests and explicitly connect corrective work'],
      ['name' => 'organization.inventory.read', 'description' => 'View parts, warehouses and stock quantities'],
      ['name' => 'organization.inventory.manage', 'description' => 'Manage parts, warehouses and motivated stock reconciliation'],
      ['name' => 'organization.inventory.consume', 'description' => 'Declare physical material use and linked returns during authorized work'],
      ['name' => 'organization.procurement.read', 'description' => 'View internal suppliers, orders and receipts'],
      ['name' => 'organization.procurement.manage', 'description' => 'Manage internal procurement and equipment individualization'],
      ['name' => 'organization.maintenance_cost.read', 'description' => 'View private internal maintenance costs, rates and valuations'],
      ['name' => 'organization.maintenance_cost.manage', 'description' => 'Configure internal currency, rates, budgets and motivated cost corrections'],
      ['name' => 'organization.maintenance_exports.read', 'description' => 'Read retained maintenance exports and external reference mappings'],
      ['name' => 'organization.maintenance_exports.manage', 'description' => 'Generate immutable validated maintenance exports and linked adjustments'],
      ['name' => 'organization.maintenance_exports.confirm', 'description' => 'Record explicit ERP import confirmations'],

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
      ['name' => 'organization.audit.export', 'description' => 'Export the organization activity feed as CSV (separate from reading it: an export leaves the product)'],

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
    $published = [];
    foreach ($definitions as $position => $definition) {
      $aliases = match ($definition['name']) {
        'organization.*' => ['*', '*.*', '*.*.*'],
        'organization.facilities.read' => ['facility.read', 'organization.facility.read'],
        default => [],
      };
      $published[] = new OrganizationPermissionDefinition($definition['name'], $definition['description'], $position, $aliases);
    }

    return $published;
  }
  // #endregion
}
