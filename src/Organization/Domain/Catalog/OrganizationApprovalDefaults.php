<?php

declare(strict_types=1);

namespace Organization\Domain\Catalog;

/**
 * Catalog OrganizationApprovalDefaults.
 *
 * Single source of truth for the four-eyes approval defaults every
 * organization starts from. Every action type is DISABLED by default
 * (approval is opt-in): organizations only store the action types and
 * fields they customize; anything absent from their settings blob resolves
 * against this catalog at read time, mirroring
 * {@see OrganizationComplianceDefaults}.
 *
 * Action type keys are stored as plain strings and are not validated here
 * (the Organization domain never depends on the Approval module); the API
 * layer validates them against the Approval action-type catalog port.
 *
 * @category Catalog
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationApprovalDefaults
{
  // #region Constants
  /**
   * Default minimum approver role required to decide on any gated action
   * type.
   */
  public const string MIN_APPROVER_ROLE = 'admin';

  /**
   * Default minimum non-conformity severity that requires approval when the
   * `nc_waiver` action type is enabled. Waivers of a lower severity apply
   * immediately even when the action type is gated.
   */
  public const string NC_WAIVER_MIN_SEVERITY = 'critical';

  /**
   * Whether the requester may also decide on their own request, by default.
   */
  public const bool ALLOW_SELF_APPROVAL = false;

  /**
   * Default number of days a pending request stays valid before it expires.
   */
  public const int APPROVAL_TTL_DAYS = 14;

  /**
   * Inclusive bounds for the approval TTL, in days.
   */
  public const int MIN_APPROVAL_TTL_DAYS = 1;

  public const int MAX_APPROVAL_TTL_DAYS = 90;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Private to prevent instantiation: this catalog only exposes constants.
   *
   * @since 1.0.0
   *
   * @codeCoverageIgnore Never executed: the constructor exists only to
   * forbid instantiation of this static catalogue.
   */
  private function __construct()
  {
  }
  // #endregion
}
