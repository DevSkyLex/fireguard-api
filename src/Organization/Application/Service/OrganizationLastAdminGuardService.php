<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationLastAdminGuardPort};
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Event\Security\OrganizationLastAdminLockoutPreventedEvent;
use Organization\Domain\Exception\OrganizationLastAdminException;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId};
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function array_intersect;
use function in_array;

/**
 * Service OrganizationLastAdminGuardService.
 *
 * Enforces the "an organization always keeps at least one active administrator"
 * invariant across member removal and role unassignment. An administrator is an
 * active member whose effective permissions grant organization.members.manage
 * (directly or through a wildcard) — the capability required to re-admit members
 * and recover the organization.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationLastAdminGuardService implements OrganizationLastAdminGuardPort
{
  /**
   * Granted permission patterns that satisfy organization.members.manage.
   *
   * Mirrors {@see OrganizationAuthorizationService::permissionMatches()} for the
   * required 'organization.members.manage': a member administers the
   * organization iff its granted permission set intersects this list.
   *
   * @var list<string>
   */
  private const array ADMIN_GRANTING_PERMISSIONS = [
    'organization.members.manage',
    'organization.members.*',
    'organization.*',
    '*',
    '*.*',
    '*.*.*',
  ];

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationLastAdminGuardService class.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $memberRepository the member repository port
   * @param OrganizationRoleRepositoryPort $roleRepository the role repository port
   * @param OrganizationAuthorizationPort $authorization the authorization port (effective permission resolution)
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port (audit trail of prevented lockouts)
   */
  public function __construct(
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private OrganizationAuthorizationPort $authorization,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  public function assertCanRemoveMember(string $organizationId, string $memberId): void
  {
    $organization = OrganizationId::fromString($organizationId);
    $member = $this->memberRepository->findById(OrganizationMemberId::fromString($memberId));

    if (null === $member || (string) $member->organizationId() !== $organizationId || !$member->isActive()) {
      // Nothing to protect: the removal handler reports not-found / no-op.
      return;
    }

    if (!$this->userIsAdmin($organization, $member->userId())) {
      // Removing a non-administrator never reduces the administrator count.
      return;
    }

    if (!$this->hasOtherActiveAdmin($organization, $member->userId())) {
      $this->eventDispatcher->dispatch(new OrganizationLastAdminLockoutPreventedEvent(
        organizationId: $organizationId,
        attemptedAction: 'remove_member',
        memberId: $memberId,
      ));

      throw OrganizationLastAdminException::cannotRemoveLastAdmin();
    }
  }

  public function assertCanUnassignRole(string $organizationId, string $memberId, string $roleId): void
  {
    $organization = OrganizationId::fromString($organizationId);
    $member = $this->memberRepository->findById(OrganizationMemberId::fromString($memberId));

    if (null === $member || (string) $member->organizationId() !== $organizationId || !$member->isActive()) {
      return;
    }

    if ($this->hasOtherActiveAdmin($organization, $member->userId())) {
      // Another active administrator remains regardless of this unassignment.
      return;
    }

    if (!$this->userIsAdmin($organization, $member->userId())) {
      // The member is not an administrator, so unassigning cannot cause lockout.
      return;
    }

    if (!$this->memberRemainsAdminAfterUnassign($member->id(), $roleId)) {
      $this->eventDispatcher->dispatch(new OrganizationLastAdminLockoutPreventedEvent(
        organizationId: $organizationId,
        attemptedAction: 'unassign_role',
        memberId: $memberId,
        roleId: $roleId,
      ));

      throw OrganizationLastAdminException::cannotUnassignLastAdminRole();
    }
  }

  public function assertCanRemoveMembers(string $organizationId, array $memberIds): void
  {
    if ([] === $memberIds) {
      return;
    }

    $organization = OrganizationId::fromString($organizationId);
    $sawAdmin = false;

    foreach ($this->memberRepository->findByOrganizationId($organization) as $member) {
      if (!$member->isActive() || !$this->userIsAdmin($organization, $member->userId())) {
        continue;
      }

      $sawAdmin = true;

      if (!in_array((string) $member->id(), $memberIds, true)) {
        // At least one administrator survives the batch.
        return;
      }
    }

    if ($sawAdmin) {
      $this->eventDispatcher->dispatch(new OrganizationLastAdminLockoutPreventedEvent(
        organizationId: $organizationId,
        attemptedAction: 'remove_members',
      ));

      throw OrganizationLastAdminException::cannotRemoveLastAdmin();
    }
  }

  public function assertCanUpdateRolePermissions(string $organizationId, string $roleId, array $newPermissions): void
  {
    $this->assertRoleChangeKeepsAdmin($organizationId, $roleId, $newPermissions, 'update_role_permissions');
  }

  public function assertCanDeleteRole(string $organizationId, string $roleId): void
  {
    $this->assertRoleChangeKeepsAdmin($organizationId, $roleId, null, 'delete_role');
  }

  /**
   * Method assertRoleChangeKeepsAdmin.
   *
   * Guards a role permission change or deletion against removing the last
   * administrator. Acts only when the role currently administers and will stop
   * doing so, then requires some active member to remain an administrator
   * through a different role.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $roleId the role identifier being changed or deleted
   * @param list<string>|null $newPermissions the role's new permissions, or null when it is deleted
   * @param string $attemptedAction the refused operation reported on the lockout-prevented event
   *
   * @throws OrganizationLastAdminException when no administrator would remain
   */
  private function assertRoleChangeKeepsAdmin(
    string $organizationId,
    string $roleId,
    ?array $newPermissions,
    string $attemptedAction,
  ): void {
    $organization = OrganizationId::fromString($organizationId);
    $role = $this->roleRepository->findById(OrganizationRoleId::fromString($roleId));

    if (null === $role || (string) $role->organizationId() !== $organizationId) {
      // Unknown / cross-organization role: the owning use case rejects it.
      return;
    }

    $currentlyGrantsAdmin = $this->isAdminPermissionSet($role->permissions());
    $stillGrantsAdmin = null !== $newPermissions && $this->isAdminPermissionSet($newPermissions);

    if (!$currentlyGrantsAdmin || $stillGrantsAdmin) {
      // The role does not administer today, or still will after the change.
      return;
    }

    foreach ($this->memberRepository->findByOrganizationId($organization) as $member) {
      if (!$member->isActive()) {
        continue;
      }

      if ($this->memberRemainsAdminAfterUnassign($member->id(), $roleId)) {
        // An active administrator survives through a different role.
        return;
      }
    }

    $this->eventDispatcher->dispatch(new OrganizationLastAdminLockoutPreventedEvent(
      organizationId: $organizationId,
      attemptedAction: $attemptedAction,
      roleId: $roleId,
    ));

    throw OrganizationLastAdminException::cannotUnassignLastAdminRole();
  }

  /**
   * Method userIsAdmin.
   *
   * Resolves whether a user administers the organization (effective permissions
   * grant organization.members.manage).
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param string $userId the user identifier
   *
   * @return bool true when the user administers the organization
   */
  private function userIsAdmin(OrganizationId $organizationId, string $userId): bool
  {
    return $this->isAdminPermissionSet(
      $this->authorization->getUserPermissions($userId, (string) $organizationId),
    );
  }

  /**
   * Method hasOtherActiveAdmin.
   *
   * Checks whether any active member other than the excluded user administers
   * the organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param string $excludedUserId the user identifier to exclude
   *
   * @return bool true when another active administrator exists
   */
  private function hasOtherActiveAdmin(OrganizationId $organizationId, string $excludedUserId): bool
  {
    foreach ($this->memberRepository->findByOrganizationId($organizationId) as $member) {
      if (!$member->isActive() || $member->userId() === $excludedUserId) {
        continue;
      }

      if ($this->userIsAdmin($organizationId, $member->userId())) {
        return true;
      }
    }

    return false;
  }

  /**
   * Method memberRemainsAdminAfterUnassign.
   *
   * Recomputes whether the member still administers the organization once the
   * given role is unassigned (union of its remaining roles' permissions).
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $memberId the member identifier
   * @param string $removedRoleId the role identifier being unassigned
   *
   * @return bool true when administration is preserved after unassignment
   */
  private function memberRemainsAdminAfterUnassign(OrganizationMemberId $memberId, string $removedRoleId): bool
  {
    $permissions = [];

    foreach ($this->memberRepository->findRoleIdsForMember($memberId) as $roleId) {
      if ($roleId === $removedRoleId) {
        continue;
      }

      $role = $this->roleRepository->findById(OrganizationRoleId::fromString($roleId));
      if (null === $role) {
        continue;
      }

      foreach ($role->permissions() as $permission) {
        $permissions[] = $permission;
      }
    }

    return $this->isAdminPermissionSet($permissions);
  }

  /**
   * Method isAdminPermissionSet.
   *
   * Determines whether a granted permission set administers the organization.
   *
   * @since 1.0.0
   *
   * @param list<string> $permissions the granted permission names
   *
   * @return bool true when the set grants organization.members.manage
   */
  private function isAdminPermissionSet(array $permissions): bool
  {
    return [] !== array_intersect($permissions, self::ADMIN_GRANTING_PERMISSIONS);
  }
  // #endregion
}
