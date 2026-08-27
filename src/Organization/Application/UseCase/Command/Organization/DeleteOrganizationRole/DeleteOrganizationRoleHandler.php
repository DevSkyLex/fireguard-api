<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeleteOrganizationRole;

use Organization\Application\Port\Inbound\OrganizationLastAdminGuardPort;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Event\Role\OrganizationRoleDeletedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\Exception\OrganizationSystemRoleImmutableException;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/**
 * UseCase DeleteOrganizationRoleHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteOrganizationRoleHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeleteOrganizationRoleHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   * @param OrganizationLastAdminGuardPort $lastAdminGuard the last-administrator lockout guard port
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
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
   * Deletes a non-system role from an organization.
   * All member role assignments are removed via cascade.
   *
   * @since 1.0.0
   *
   * @param DeleteOrganizationRoleCommand $command the command payload
   *
   * @return DeleteOrganizationRoleResult the use case result
   */
  public function __invoke(DeleteOrganizationRoleCommand $command): DeleteOrganizationRoleResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $roleId = OrganizationRoleId::fromString($command->roleId);

    // Deleting the only admin-granting role locks everyone out just as surely as
    // removing the last administrator, so the check and the delete share one
    // transaction and the guard's advisory lock (see the guard port contract).
    /** @var string $roleName */
    $roleName = $this->transactionManager->transactional(
      function () use ($command, $organizationId, $roleId): string {
        $this->lastAdminGuard->assertCanDeleteRole($command->organizationId, $command->roleId);

        $role = $this->roleRepository->findById($roleId);

        if (null === $role || (string) $role->organizationId() !== (string) $organizationId) {
          throw OrganizationRoleNotFoundException::withId($command->roleId);
        }

        if ($role->isSystem()) {
          throw OrganizationSystemRoleImmutableException::cannotBeDeleted();
        }

        $roleName = (string) $role->name();

        $this->roleRepository->remove($role);

        return $roleName;
      },
    );

    $this->eventDispatcher->dispatch(new OrganizationRoleDeletedEvent(
      organizationId: $command->organizationId,
      roleId: $command->roleId,
      roleName: $roleName,
    ));

    return new DeleteOrganizationRoleResult(
      roleId: (string) $roleId,
      organizationId: (string) $organizationId,
    );
  }
  // #endregion
}
