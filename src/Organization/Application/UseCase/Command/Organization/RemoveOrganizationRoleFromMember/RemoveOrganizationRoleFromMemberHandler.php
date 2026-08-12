<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RemoveOrganizationRoleFromMember;

use Organization\Application\Port\Inbound\OrganizationLastAdminGuardPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Event\Role\OrganizationRoleUnassignedEvent;
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/**
 * UseCase RemoveOrganizationRoleFromMemberHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveOrganizationRoleFromMemberHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RemoveOrganizationRoleFromMemberHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository port
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher
   * @param OrganizationLastAdminGuardPort $lastAdminGuard the last-administrator lockout guard port
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private EventDispatcherPort $eventDispatcher,
    private OrganizationLastAdminGuardPort $lastAdminGuard,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Removes a role assignment from a member.
   *
   * @since 1.0.0
   *
   * @param RemoveOrganizationRoleFromMemberCommand $command the command payload
   *
   * @return RemoveOrganizationRoleFromMemberResult the use case result
   */
  public function __invoke(RemoveOrganizationRoleFromMemberCommand $command): RemoveOrganizationRoleFromMemberResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $memberId = OrganizationMemberId::fromString($command->memberId);
    $roleId = OrganizationRoleId::fromString($command->roleId);

    // Same transaction, same advisory lock as the check: stripping the admin role
    // from the last administrator must not slip past a concurrent removal that
    // read the organization as still having one (see the guard port contract).
    $this->transactionManager->transactional(
      function () use ($command, $organizationId, $memberId, $roleId): void {
        $this->lastAdminGuard->assertCanUnassignRole(
          $command->organizationId,
          $command->memberId,
          $command->roleId,
        );

        $member = $this->memberRepository->findById($memberId);

        if (null === $member || (string) $member->organizationId() !== (string) $organizationId) {
          throw OrganizationMemberNotFoundException::withId($command->memberId);
        }

        $role = $this->roleRepository->findById($roleId);

        if (null === $role || (string) $role->organizationId() !== (string) $organizationId) {
          throw OrganizationRoleNotFoundException::withId($command->roleId);
        }

        $this->memberRepository->unassignRole($memberId, $roleId);
      },
    );

    $this->eventDispatcher->dispatch(new OrganizationRoleUnassignedEvent(
      organizationId: $command->organizationId,
      memberId: $command->memberId,
      roleId: $command->roleId,
    ));

    return new RemoveOrganizationRoleFromMemberResult(
      memberId: (string) $memberId,
      organizationId: (string) $organizationId,
      roleId: (string) $roleId,
    );
  }
  // #endregion
}
