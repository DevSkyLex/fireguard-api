<?php

declare(strict_types=1);

namespace Calendar\Application\Port\Outbound\Member;

/**
 * Port CalendarMemberDirectoryPort.
 *
 * Cross-module outbound port toward Organization, implemented by
 * `Organization\Infrastructure\Adapter\Calendar\OrganizationCalendarMemberDirectoryAdapter`.
 * Used to attribute a created event to an organization member id, mirroring
 * `Messaging\Application\Port\Outbound\MessagingMemberDirectoryPort`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CalendarMemberDirectoryPort
{
  // #region Methods
  /**
   * Method resolveActiveMemberId.
   *
   * Resolves the authenticated user's organization member identifier,
   * provided the membership is active.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $userId the user identifier
   *
   * @return ?string the resolved active member identifier, or null
   */
  public function resolveActiveMemberId(string $organizationId, string $userId): ?string;
  // #endregion
}
