<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Messaging;

use Messaging\Application\Port\Outbound\MessagingMemberDirectoryPort;
use Organization\Application\Port\Outbound\Internal\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use Shared\Domain\Exception\InvalidValueException;
use Throwable;
use User\Application\Contract\User\UserView;
use User\Application\Port\Inbound\UserDirectoryPort;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function in_array;
use function trim;

/**
 * Adapter OrganizationMessagingMemberDirectoryAdapter.
 *
 * Implements Messaging's `MessagingMemberDirectoryPort`. Member identifiers
 * validated here frequently come from user input (parsed `@{uuid}` mention
 * tokens), so a malformed identifier resolves to `null`/`false` rather than
 * throwing.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationMessagingMemberDirectoryAdapter implements MessagingMemberDirectoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $members the organization member repository port
   * @param UserDirectoryPort $queryBus the query bus, used to reach the user behind a member
   */
  public function __construct(
    private OrganizationMemberRepositoryPort $members,
    private UserDirectoryPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method resolveActiveMemberId.
   *
   * Resolves the user's active organization member identifier, returning null for invalid IDs.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $userId the local user identifier
   *
   * @return ?string active member identifier, or null when no active membership exists
   */
  public function resolveActiveMemberId(string $organizationId, string $userId): ?string
  {
    try {
      $member = $this->members->findByOrganizationAndUser(OrganizationId::fromString($organizationId), $userId);
    } catch (InvalidValueException) {
      return null;
    }

    return null !== $member && $member->isActive() ? $member->id()->value : null;
  }

  /**
   * Method memberIsActive.
   *
   * Checks that a member identifier belongs to this organization and is active.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $memberId the organization member identifier
   *
   * @return bool whether the member is active in this organization
   */
  public function memberIsActive(string $organizationId, string $memberId): bool
  {
    try {
      $member = $this->members->findById(OrganizationMemberId::fromString($memberId));
    } catch (InvalidValueException) {
      return false;
    }

    return null !== $member && $member->isActive() && $member->organizationId()->value === $organizationId;
  }

  /**
   * Method resolveUserIdForMember.
   *
   * Resolves a member's local user identifier only when it belongs to this organization.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $memberId the organization member identifier
   *
   * @return ?string local user identifier, or null when the member is absent or out of scope
   */
  public function resolveUserIdForMember(string $organizationId, string $memberId): ?string
  {
    try {
      $member = $this->members->findById(OrganizationMemberId::fromString($memberId));
    } catch (InvalidValueException) {
      return null;
    }

    if (null === $member || $member->organizationId()->value !== $organizationId) {
      return null;
    }

    return $member->userId();
  }

  /**
   * Method displayNamesFor.
   *
   * Resolves human-readable names for supplied members in one organization-scoped pass.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param list<string> $memberIds member identifiers to resolve
   *
   * @return array<string, string> available display names keyed by member identifier
   */
  public function displayNamesFor(string $organizationId, array $memberIds): array
  {
    if ([] === $memberIds) {
      return [];
    }

    try {
      $members = $this->members->findByOrganizationId(OrganizationId::fromString($organizationId));
      $requested = array_filter($members, static fn (OrganizationMember $member): bool => in_array($member->id()->value, $memberIds, true));

      return $this->displayNamesForMembers($requested);
    } catch (InvalidValueException) {
      return [];
    }
  }

  /**
   * Method activeUserIdsForMembers.
   *
   * Resolves local user identifiers for active members in the organization.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param list<string> $memberIds member identifiers to resolve
   *
   * @return array<string, string> active member identifier to local user identifier map
   */
  public function activeUserIdsForMembers(string $organizationId, array $memberIds): array
  {
    try {
      return $this->members->findUserIdsByMemberIds(OrganizationId::fromString($organizationId), $memberIds, true);
    } catch (InvalidValueException) {
      return [];
    }
  }

  /**
   * Method activeMembershipsForUser.
   *
   * Returns the active organization membership identities for a local user.
   *
   * @access public
   *
   * @param string $userId the local user identifier
   *
   * @return list<array{organizationId: string, memberId: string}> the user's active membership identities
   */
  public function activeMembershipsForUser(string $userId): array
  {
    return $this->members->findActiveMembershipIdentitiesForUser($userId);
  }
  // #endregion

  // #region Internals
  /**
   * Method displayNamesForMembers.
   *
   * Reads account profiles once for the already scoped membership projection.
   *
   * @access private
   *
   * @param array<OrganizationMember> $requested the requested organization members
   *
   * @return array<string, string> available names keyed by member identifier
   */
  private function displayNamesForMembers(array $requested): array
  {
    try {
      $users = $this->queryBus->findByIds(array_values(array_unique(array_map(static fn (OrganizationMember $member): string => $member->userId(), $requested))));
    } catch (Throwable) {
      return [];
    }
    $names = [];
    foreach ($requested as $member) {
      $name = $this->displayNameFor($users[$member->userId()] ?? null);
      if (null !== $name) {
        $names[$member->id()->value] = $name;
      }
    }

    return $names;
  }

  /**
   * Method displayNameFor.
   *
   * Derives a member's human-readable name from the user behind it, mirroring
   * `ListOrganizationMembersProvider`.
   *
   * Returns `null` rather than the user identifier when the lookup yields
   * nothing: a caller handed no name can say so, whereas a caller handed a UUID
   * renders it as if it were a person — which is the defect this method exists
   * to remove.
   *
   * @since 1.1.0
   *
   * @param ?UserView $user the published user projection
   *
   * @return ?string the display name, or null when it cannot be derived
   */
  private function displayNameFor(?UserView $user): ?string
  {
    if (null === $user) {
      return null;
    }

    return trim($user->firstName . ' ' . $user->lastName) ?: ($user->username ?: null);
  }

  // #endregion
}
