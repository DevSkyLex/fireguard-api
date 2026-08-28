<?php

declare(strict_types=1);

namespace Notification\Application\Contract\Notification;

use function explode;
use function in_array;

/**
 * Contract NotificationType.
 *
 * Defines all known notification type constants organized by category.
 * Lives in Application/Contract because sibling modules name these types when
 * they publish a notification: a Domain type must not cross a module boundary.
 *
 * Type format: `{category}.{action}` — e.g. `organization.invitation`.
 * The category is the prefix before the first dot and drives
 * how the notification is rendered and grouped on the client side.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationType
{
  // #region Categories
  public const string CATEGORY_SYSTEM = 'system';

  public const string CATEGORY_ORGANIZATION = 'organization';

  public const string CATEGORY_USER = 'user';

  public const string CATEGORY_FACILITY = 'facility';

  public const string CATEGORY_EQUIPMENT = 'equipment';
  // #endregion

  // #region System types
  /**
   * Platform-wide announcement (maintenance window, new features, …).
   */
  public const string SYSTEM_ANNOUNCEMENT = 'system.announcement';

  /**
   * Scheduled or unplanned maintenance notification.
   */
  public const string SYSTEM_MAINTENANCE = 'system.maintenance';
  // #endregion

  // #region Organization types
  /**
   * Sent when a user is invited to join an organization.
   */
  public const string ORGANIZATION_INVITATION = 'organization.invitation';

  /**
   * Sent to the inviter when the invitation is accepted.
   */
  public const string ORGANIZATION_INVITATION_ACCEPTED = 'organization.invitation_accepted';

  /**
   * Sent to the invited user when their invitation is revoked.
   */
  public const string ORGANIZATION_INVITATION_REVOKED = 'organization.invitation_revoked';

  /**
   * Sent to organization owners/admins when a new member joins.
   */
  public const string ORGANIZATION_MEMBER_JOINED = 'organization.member_joined';

  /**
   * Sent to a user when an admin grants organization access.
   */
  public const string ORGANIZATION_MEMBER_ADDED = 'organization.member_added';

  /**
   * Sent to a member when their organization access is removed.
   */
  public const string ORGANIZATION_MEMBER_REMOVED = 'organization.member_removed';

  /**
   * Constant ORGANIZATION_PLAN_OVER_QUOTA.
   *
   * The organization's plan changed while its current usage exceeds the new
   * plan's caps (typically a Stripe downgrade): existing data is preserved
   * but new creations stay blocked until usage fits the plan.
   */
  public const string ORGANIZATION_PLAN_OVER_QUOTA = 'organization.plan_over_quota';

  /**
   * Constant ORGANIZATION_WEEKLY_DIGEST.
   *
   * Weekly operational summary sent to an organization's administrators:
   * overdue interventions, upcoming/overdue maintenance deadlines, and open
   * non-conformities.
   */
  public const string ORGANIZATION_WEEKLY_DIGEST = 'organization.weekly_digest';
  // #endregion

  // #region User types
  /**
   * Sent to a user when their e-mail address is verified.
   */
  public const string USER_EMAIL_VERIFIED = 'user.email_verified';
  // #endregion

  // #region Facility types
  /**
   * Sent when a facility is archived.
   */
  public const string FACILITY_ARCHIVED = 'facility.archived';
  // #endregion

  // #region Equipment types
  /**
   * Sent when equipment is put under maintenance.
   */
  public const string EQUIPMENT_UNDER_MAINTENANCE = 'equipment.under_maintenance';
  // #endregion

  // #region Methods
  /**
   * Method all.
   *
   * @since 1.0.0
   *
   * @return list<string> all known type constants
   */
  public static function all(): array
  {
    return [
      self::SYSTEM_ANNOUNCEMENT,
      self::SYSTEM_MAINTENANCE,
      self::ORGANIZATION_INVITATION,
      self::ORGANIZATION_INVITATION_ACCEPTED,
      self::ORGANIZATION_INVITATION_REVOKED,
      self::ORGANIZATION_MEMBER_JOINED,
      self::ORGANIZATION_PLAN_OVER_QUOTA,
      self::ORGANIZATION_WEEKLY_DIGEST,
      self::ORGANIZATION_MEMBER_ADDED,
      self::ORGANIZATION_MEMBER_REMOVED,
      self::USER_EMAIL_VERIFIED,
      self::FACILITY_ARCHIVED,
      self::EQUIPMENT_UNDER_MAINTENANCE,
    ];
  }

  /**
   * Method isValid.
   *
   * Returns true for known types. Unknown types are still accepted by
   * the domain on purpose, to allow forward-compatibility with types
   * added without a server-side code deployment.
   *
   * @since 1.0.0
   *
   * @param string $type the type string to check
   *
   * @return bool true when `$type` belongs to the known set
   */
  public static function isValid(string $type): bool
  {
    return in_array($type, self::all(), true);
  }

  /**
   * Method category.
   *
   * Extracts the category from a type string by returning the segment
   * before the first dot. Falls back to the full string when no dot
   * is present (e.g. a legacy bare type).
   *
   * Examples:
   *   `organization.invitation`    → `organization`
   *   `system.announcement`        → `system`
   *   `legacy`                     → `legacy`
   *
   * @since 1.0.0
   *
   * @param string $type the dot-notation type string
   *
   * @return string the category segment
   */
  public static function category(string $type): string
  {
    return explode('.', $type, 2)[0];
  }
  // #endregion
}
