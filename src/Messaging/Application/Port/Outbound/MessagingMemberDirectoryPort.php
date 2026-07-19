<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

/**
 * Port MessagingMemberDirectoryPort.
 *
 * Cross-module outbound port toward Organization, implemented by
 * `Organization\Infrastructure\Adapter\Messaging\OrganizationMessagingMemberDirectoryAdapter`.
 * Used to attribute an authenticated action to an organization member id and
 * to validate `@{memberUuid}` mention tokens (which come from user input).
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MessagingMemberDirectoryPort
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

  /**
   * Method memberIsActive.
   *
   * Checks whether a member identifier is an active member of the given
   * organization — used to validate mention tokens, which come from user
   * input.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $memberId the member identifier
   *
   * @return bool true when the member is active in the organization
   */
  public function memberIsActive(string $organizationId, string $memberId): bool;

  /**
   * Method resolveUserIdForMember.
   *
   * Resolves the user identifier behind an organization member, for
   * notification delivery.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $memberId the member identifier
   *
   * @return ?string the resolved user identifier, or null
   */
  public function resolveUserIdForMember(string $organizationId, string $memberId): ?string;
  // #endregion
}
