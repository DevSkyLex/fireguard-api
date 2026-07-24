<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Domain\ValueObject\OrganizationNotificationSettings;

/**
 * Port OrganizationNotificationPolicyPort.
 *
 * Inbound contract used across modules to resolve an organization's effective
 * notification policy (enabled delivery channels and event categories) before a
 * notification is emitted. It is the single seam other modules consult so the
 * organization-level toggles actually gate delivery instead of being decorative.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationNotificationPolicyPort
{
  // #region Methods
  /**
   * Method notificationPolicy.
   *
   * Resolves the notification policy for an organization. Implementations must
   * never throw on an unknown or malformed identifier: they fall back to the
   * permissive "all on" defaults so a lookup failure can never silently suppress
   * delivery.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return OrganizationNotificationSettings the effective notification policy
   */
  public function notificationPolicy(string $organizationId): OrganizationNotificationSettings;
  // #endregion
}
