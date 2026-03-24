<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\AssignOrganizationRoleToMember;

use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId};
use Shared\Application\Message\CommandHandler;

/**
 * UseCase AssignOrganizationRoleToMemberHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssignOrganizationRoleToMemberHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AssignOrganizationRoleToMemberHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository
   */
  public function __construct(
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
  ) {
  }
  // #endregion

  // #region Methods

  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param AssignOrganizationRoleToMemberCommand $command the command payload
   */
  public function __invoke(AssignOrganizationRoleToMemberCommand $command): AssignOrganizationRoleToMemberResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $memberId = OrganizationMemberId::fromString($command->memberId);
    $roleId = OrganizationRoleId::fromString($command->roleId);

    $member = $this->memberRepository->findById($memberId);
    if (null === $member || (string) $member->organizationId() !== (string) $organizationId) {
      throw OrganizationMemberNotFoundException::withId($command->memberId);
    }

    $role = $this->roleRepository->findById($roleId);
    if (null === $role || (string) $role->organizationId() !== (string) $organizationId) {
      throw OrganizationRoleNotFoundException::withId($command->roleId);
    }

    $this->memberRepository->assignRole($memberId, $roleId);

    return new AssignOrganizationRoleToMemberResult(
      memberId: (string) $memberId,
      organizationId: (string) $organizationId,
      roleId: (string) $roleId,
      roleIds: $this->memberRepository->findRoleIdsForMember($memberId),
      userId: $member->userId(),
      isActive: $member->isActive(),
      joinedAt: $member->joinedAt(),
    );
  }
  // #endregion
}
