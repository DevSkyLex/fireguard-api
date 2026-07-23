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

  /**
   * Method displayNamesFor.
   *
   * Resolves human-readable names for a batch of organization members.
   *
   * Batch, and not one call per member, because the only caller maps a whole
   * page of messages: an author, their mentions and a pinning member each need
   * a name, and resolving them one at a time is the N+1 this contract exists to
   * avoid.
   *
   * A member that cannot be resolved is simply absent from the result — the
   * caller decides what to show for it, and never renders a raw identifier
   * because the map said nothing.
   *
   * @since 1.1.0
   *
   * @param string $organizationId the organization identifier
   * @param list<string> $memberIds the member identifiers to resolve
   *
   * @return array<string, string> display names indexed by member identifier
   */
  public function displayNamesFor(string $organizationId, array $memberIds): array;
  // #endregion
}
