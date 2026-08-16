<?php

declare(strict_types=1);

namespace Audit\Application\Service;

use function array_key_exists;

/**
 * Service OrganizationAuditMetadataProjection.
 *
 * Per-action allowlist deciding which metadata keys of an organization-scoped
 * ledger row may cross the platform trust boundary into
 * {@see \Audit\Application\Contract\OrganizationAuditEntry}.
 *
 * **It is an allowlist on purpose.** The predecessor of this class was a
 * denylist of key *names* (`ip`, `user_agent`, anything containing `email`…),
 * which is unsound twice over: it cannot see inside a value, and it lets any
 * key a future producer invents through by default. Organization-scoped
 * producers genuinely emit operator-typed prose — `reason`, `context` — and
 * raw exception text — `error`, `job_error` — under perfectly innocent key
 * names. So the default is "drop": an action absent from `ALLOWED_KEYS`
 * projects to an empty payload, and a key absent from its action's entry is
 * dropped. Adding an org-scoped action without an entry here degrades the
 * feed; it cannot leak through it.
 *
 * The admission rule for a key, applied when this map is extended:
 * identifiers, catalog/enum keys, booleans, counts, and organization-owned
 * entity labels are admissible. Anything that can carry operator-typed prose
 * (`reason`, `context`), system internals (`error`, `job_error` — exception
 * text embeds file paths and row fragments), personal data (emails, IPs, user
 * agents), or data belonging to another permission's surface
 * (`invited_email` → `organization.members.manage`, `url_host` →
 * `organization.webhooks.*`) is not — the feed must never become a way around
 * a permission the caller was not granted.
 *
 * `organization_id` is dropped from every action: the reader already scoped
 * the request to one organization, so echoing it back is noise.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAuditMetadataProjection
{
  // #region Constants
  /**
   * Constant ALLOWED_KEYS.
   *
   * Audit action key => the metadata keys that action may publish to an
   * organization audience. An action missing here projects to `[]`.
   *
   * @since 1.0.0
   *
   * @var array<string, list<string>>
   */
  private const array ALLOWED_KEYS = [
    // Organization lifecycle
    'organization.created' => ['name', 'owner_user_id'],
    'organization.archived' => [],
    'organization.restored' => ['previous_status'],
    'organization.suspended' => [],
    'organization.settings_updated' => ['changed_fields'],
    'organization.plan_changed' => ['plan_id', 'previous_plan_id', 'over_quota_resources'],
    'organization.ownership_transferred' => ['previous_owner_user_id', 'new_owner_user_id'],

    // Roles and permissions
    'organization.role_created' => ['role_name', 'permissions'],
    'organization.role_updated' => ['role_name', 'permissions'],
    'organization.role_deleted' => ['role_name'],
    'organization.role_assigned' => ['role_id', 'role_name'],
    'organization.role_unassigned' => ['role_id'],
    // 'context' is operator-typed prose describing the refused grant.
    'organization.permission_grant_denied' => ['denied_permission'],
    'organization.last_admin_lockout_prevented' => ['attempted_action', 'member_id', 'role_id'],

    // Membership and invitations
    'organization.member_added' => ['user_id', 'role_ids'],
    'organization.member_removed' => ['user_id'],
    // 'invited_email'/'invited_email_hash' belong to the invitations surface
    // (organization.members.manage), not to this one.
    'organization.invitation_sent' => ['resend'],
    'organization.invitation_accepted' => ['member_id', 'role_ids'],
    // 'reason' is free text typed by the revoker.
    'organization.invitation_revoked' => [],

    // Teams
    'organization.team_created' => ['name'],
    'organization.team_updated' => ['name'],
    'organization.team_deleted' => [],
    'organization.team_member_added' => ['team_id', 'role'],
    'organization.team_member_removed' => ['team_id'],

    // Facilities
    'facility.archived' => [],
    'facility.restored' => [],
    'facility.moved' => ['previous_parent_facility_id', 'new_parent_facility_id'],
    'facility.subtree_duplicated' => ['new_root_facility_id', 'node_count'],

    // Equipment
    'equipment.commissioned' => ['facility_id', 'previous_status'],
    'equipment.under_maintenance' => ['facility_id', 'previous_status'],
    'equipment.returned_to_stock' => ['previous_status'],
    'equipment.decommissioned' => ['previous_status'],

    // Inspections
    'inspection.submitted' => ['equipment_id', 'result'],
    'inspection.closed' => ['equipment_id', 'result'],
    'inspection.cancelled' => ['equipment_id', 'previous_status'],
    'inspection.non_conformity_recorded' => ['inspection_id', 'severity'],
    'inspection.non_conformity_status_changed' => ['inspection_id', 'previous_status', 'status'],

    // Interventions
    'intervention.published' => ['publication_id'],
    // 'reason' is the free-text publication failure description.
    'intervention.publication_failed' => ['publication_id'],
    'intervention.recurrence_created' => ['template_id'],
    'intervention.recurrence_updated' => [],
    'intervention.recurrence_deleted' => [],
    // 'error' is raw exception text.
    'intervention.recurrence_materialized' => ['succeeded', 'intervention_id'],

    // Maintenance
    'maintenance.schedule_overridden' => ['equipment_id', 'interval_override'],
    'maintenance.campaign_generated' => ['work_items_count'],

    // Automation
    'automation.rule_executed' => ['rule_key', 'intervention_id'],
    // 'error' is raw exception text.
    'automation.rule_failed' => ['rule_key'],

    // Messaging — channel and conversation names are deliberately absent:
    // a channel can be a restricted conversation, and its label belongs to
    // the messaging surface, not to the audit feed.
    'messaging.conversation_archived' => ['subject_type', 'subject_id'],
    'messaging.message_moderated' => ['conversation_id', 'author_member_id'],
    'messaging.message_unpin_moderated' => ['conversation_id', 'pinned_by_member_id'],
    'messaging.channel_created' => ['created_by_member_id'],
    'messaging.channel_participant_added' => ['member_id'],
    'messaging.channel_participant_removed' => ['member_id'],
    'messaging.channel_team_binding_changed' => ['team_id'],
    'messaging.channel_parent_changed' => ['parent_conversation_id'],

    // Imports
    'import.job_completed' => ['kind', 'total_rows', 'successful_rows', 'failed_rows'],
    // 'job_error' is raw exception text and can embed source filenames and
    // fragments of the imported rows.
    'import.job_failed' => ['kind'],

    // Compliance
    'compliance.register_exported' => ['scope', 'plan_key', 'generated_at'],

    // Webhooks — 'url_host' belongs to the organization.webhooks.* surface.
    'webhook.subscription_created' => ['event_types'],
    'webhook.subscription_deleted' => [],

    // Four-eyes approvals
    'approval.requested' => ['action_type', 'subject_id', 'requested_by_member_id'],
    'approval.approved' => ['action_type', 'subject_id', 'decision_by_member_id'],
    'approval.rejected' => ['action_type', 'subject_id', 'decision_by_member_id'],
    'approval.expired' => ['action_type', 'subject_id'],
    // 'error' is raw exception text.
    'approval.execution_failed' => ['action_type', 'subject_id'],
  ];
  // #endregion

  // #region Methods
  /**
   * Method project.
   *
   * Reduces a raw metadata payload to the keys the action is allowed to
   * publish to an organization audience, preserving the allowlist's order so
   * the wire shape does not depend on producer insertion order.
   *
   * @since 1.0.0
   *
   * @param string $action the audit action key
   * @param array<string, mixed> $metadata the raw producer payload
   *
   * @return array<string, mixed> the projected payload, empty when the action is unknown
   */
  public static function project(string $action, array $metadata): array
  {
    $allowed = self::ALLOWED_KEYS[$action] ?? [];

    $projected = [];
    foreach ($allowed as $key) {
      if (array_key_exists($key, $metadata)) {
        $projected[$key] = $metadata[$key];
      }
    }

    return $projected;
  }

  /**
   * Method knownActions.
   *
   * Returns every action key the projection knows about. Exposed for the
   * guard test that asserts no allowlist ever admits a prose, credential, or
   * request-context key.
   *
   * @since 1.0.0
   *
   * @return array<string, list<string>> the action => allowed keys map
   */
  public static function knownActions(): array
  {
    return self::ALLOWED_KEYS;
  }
  // #endregion
}
