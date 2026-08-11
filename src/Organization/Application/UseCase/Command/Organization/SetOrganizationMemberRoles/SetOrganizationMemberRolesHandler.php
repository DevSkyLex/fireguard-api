<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\SetOrganizationMemberRoles;

use Organization\Application\Port\Inbound\{OrganizationLastAdminGuardPort, OrganizationPermissionGrantGuardPort};
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Event\Role\{OrganizationRoleAssignedEvent, OrganizationRoleUnassignedEvent};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

use function array_diff;
use function array_map;
use function array_unique;
use function array_values;
use function count;

/**
 * UseCase SetOrganizationMemberRolesHandler.
 *
 * Replaces a member's entire role set in one call (bulk assignment), so a
 * caller does not have to issue N unit AssignOrganizationRoleToMember /
 * RemoveOrganizationRoleFromMember calls. The diff between the member's
 * current roles and the requested set is computed first, then each half is
 * guarded exactly as the unit operations are: the roles being granted go
 * through {@see OrganizationPermissionGrantGuardPort::assertCanAssignRoles()}
 * (no-privilege-escalation), and each role being revoked goes through
 * {@see OrganizationLastAdminGuardPort::assertCanUnassignRole()} (last-admin
 * lockout prevention). Only the roles that actually change are persisted and
 * only actual changes raise an event — a no-op diff dispatches nothing.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetOrganizationMemberRolesHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the SetOrganizationMemberRolesHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository port
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository port
   * @param OrganizationPermissionGrantGuardPort $grantGuard the no-privilege-escalation role-grant guard port
   * @param OrganizationLastAdminGuardPort $lastAdminGuard the last-administrator lockout guard port
   * @param TransactionManagerPort $transactionManager the transaction manager
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher port
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private OrganizationPermissionGrantGuardPort $grantGuard,
    private OrganizationLastAdminGuardPort $lastAdminGuard,
    private TransactionManagerPort $transactionManager,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Replaces the member's role set with the requested one.
   *
   * @since 1.0.0
   *
   * @param SetOrganizationMemberRolesCommand $command the command payload
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationMemberNotFoundException when the member does not
   *                                             belong to the organization
   *                                             or is not active
   * @throws OrganizationRoleNotFoundException when a requested role id does
   *                                           not exist in this organization
   *
   * @return SetOrganizationMemberRolesResult the use case result
   */
  public function __invoke(SetOrganizationMemberRolesCommand $command): SetOrganizationMemberRolesResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $memberId = OrganizationMemberId::fromString($command->memberId);
    $member = $this->memberRepository->findById($memberId);

    if (null === $member || (string) $member->organizationId() !== (string) $organizationId || !$member->isActive()) {
      throw OrganizationMemberNotFoundException::withId($command->memberId);
    }

    /** @var list<string> $requestedRoleIds */
    $requestedRoleIds = array_values(array_unique($command->roleIds));

    /** @var list<OrganizationRoleId> $requestedRoleIdsAsVo */
    $requestedRoleIdsAsVo = array_map(
      static fn (string $roleId): OrganizationRoleId => OrganizationRoleId::fromString($roleId),
      $requestedRoleIds,
    );

    $roles = $this->roleRepository->findByIdsInOrganization($organizationId, $requestedRoleIdsAsVo);

    if (count($roles) !== count($requestedRoleIdsAsVo)) {
      throw OrganizationRoleNotFoundException::withId('one-or-more-role-ids');
    }

    /** @var array<string, OrganizationRole> $rolesById */
    $rolesById = [];
    foreach ($roles as $role) {
      $rolesById[(string) $role->id()] = $role;
    }

    $currentRoleIds = $this->memberRepository->findRoleIdsForMember($memberId);

    /** @var list<string> $toAssign */
    $toAssign = array_values(array_diff($requestedRoleIds, $currentRoleIds));
    /** @var list<string> $toUnassign */
    $toUnassign = array_values(array_diff($currentRoleIds, $requestedRoleIds));

    if ([] !== $toAssign) {
      $this->grantGuard->assertCanAssignRoles($command->actingUserId, $command->organizationId, $toAssign);
    }

    foreach ($toUnassign as $roleId) {
      $this->lastAdminGuard->assertCanUnassignRole($command->organizationId, $command->memberId, $roleId);
    }

    if ([] !== $toAssign || [] !== $toUnassign) {
      $this->transactionManager->transactional(function () use ($memberId, $toAssign, $toUnassign): void {
        foreach ($toAssign as $roleId) {
          $this->memberRepository->assignRole($memberId, OrganizationRoleId::fromString($roleId));
        }

        foreach ($toUnassign as $roleId) {
          $this->memberRepository->unassignRole($memberId, OrganizationRoleId::fromString($roleId));
        }
      });
    }

    foreach ($toAssign as $roleId) {
      $role = $rolesById[$roleId] ?? null;

      $this->eventDispatcher->dispatch(new OrganizationRoleAssignedEvent(
        organizationId: $command->organizationId,
        memberId: $command->memberId,
        roleId: $roleId,
        roleName: $role instanceof OrganizationRole ? (string) $role->name() : '',
      ));
    }

    foreach ($toUnassign as $roleId) {
      $this->eventDispatcher->dispatch(new OrganizationRoleUnassignedEvent(
        organizationId: $command->organizationId,
        memberId: $command->memberId,
        roleId: $roleId,
      ));
    }

    return new SetOrganizationMemberRolesResult(
      memberId: (string) $memberId,
      organizationId: (string) $organizationId,
      userId: $member->userId(),
      roleIds: $this->memberRepository->findRoleIdsForMember($memberId),
      isActive: $member->isActive(),
      joinedAt: $member->joinedAt(),
    );
  }
  // #endregion
}
