<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Port\Inbound\OrganizationCallerMembershipPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationCallerRoleResult;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId};

use function array_map;

/**
 * Service OrganizationCallerMembershipService.
 *
 * Implements {@see OrganizationCallerMembershipPort} — the projection was
 * previously duplicated inline in
 * `ListUserOrganizationsHandler::resolveCallerRoles()`, with
 * `GetOrganizationHandler` (and every mutation processor re-reading through
 * it) never resolving it at all. Moving it here lets both use cases, and
 * therefore `GET /organizations/{id}` plus the suspend/restore/
 * transfer-ownership/settings-update mutation outputs, share the exact same
 * rule.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationCallerMembershipService implements OrganizationCallerMembershipPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository port
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository port
   */
  public function __construct(
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method isOwner.
   *
   * Checks whether the caller is the organization's recorded owner.
   *
   * @access public
   *
   * @param string $organizationOwnerUserId the organization owner's user identifier
   * @param string $callerUserId the caller's user identifier
   *
   * @return bool whether the caller owns the organization
   */
  public function isOwner(string $organizationOwnerUserId, string $callerUserId): bool
  {
    return $organizationOwnerUserId === $callerUserId;
  }

  /**
   * Method findActiveCallerMembership.
   *
   * Loads the caller's organization membership and excludes inactive memberships.
   *
   * @access public
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param string $callerUserId the caller's user identifier
   *
   * @return ?OrganizationMember the active membership, or null when absent or inactive
   */
  public function findActiveCallerMembership(OrganizationId $organizationId, string $callerUserId): ?OrganizationMember
  {
    $membership = $this->memberRepository->findByOrganizationAndUser($organizationId, $callerUserId);

    return null !== $membership && $membership->isActive() ? $membership : null;
  }

  /**
   * Method resolveRoles.
   *
   * Resolves the active membership's organization roles into caller-role results.
   *
   * @access public
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param ?OrganizationMember $membership the caller's active membership, when present
   *
   * @return list<GetOrganizationCallerRoleResult> the roles belonging to this organization
   */
  public function resolveRoles(OrganizationId $organizationId, ?OrganizationMember $membership): array
  {
    if (null === $membership) {
      return [];
    }

    $roleIds = array_map(
      static fn (string $roleId): OrganizationRoleId => OrganizationRoleId::fromString($roleId),
      $this->memberRepository->findRoleIdsForMember($membership->id()),
    );

    if ([] === $roleIds) {
      return [];
    }

    $roles = [];
    foreach ($this->roleRepository->findByIdsInOrganization($organizationId, $roleIds) as $role) {
      $roles[] = new GetOrganizationCallerRoleResult(
        id: (string) $role->id(),
        label: (string) $role->name(),
      );
    }

    return $roles;
  }
  // #endregion
}
