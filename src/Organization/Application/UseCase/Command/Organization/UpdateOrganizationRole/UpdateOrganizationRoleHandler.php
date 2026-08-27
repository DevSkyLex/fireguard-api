<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\UpdateOrganizationRole;

use Organization\Application\Port\Inbound\OrganizationLastAdminGuardPort;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Event\Role\OrganizationRoleUpdatedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\Exception\{OrganizationRoleNameAlreadyExistsException, OrganizationSystemRoleImmutableException};
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;

use function array_unique;
use function array_values;
use function count;

/**
 * UseCase UpdateOrganizationRoleHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateOrganizationRoleHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UpdateOrganizationRoleHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
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
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param UpdateOrganizationRoleCommand $command the command payload
   */
  public function __invoke(UpdateOrganizationRoleCommand $command): UpdateOrganizationRoleResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $roleId = OrganizationRoleId::fromString($command->roleId);

    /** @var list<string> $permissions */
    $permissions = array_values(array_unique($command->permissions));

    if (0 === count($permissions)) {
      throw InvalidValueException::because('At least one permission is required.');
    }

    // Two check-then-writes share this transaction. Stripping
    // organization.members.manage from the only admin-granting role is a lockout
    // by another route, so the guard's census and this save must be serialized by
    // the advisory lock (see the guard port contract); and the rename's
    // name-uniqueness lookup must not sit outside the transaction that persists
    // the new name either.
    /** @var OrganizationRole $role */
    $role = $this->transactionManager->transactional(
      function () use ($command, $organizationId, $roleId, $permissions): OrganizationRole {
        $this->lastAdminGuard->assertCanUpdateRolePermissions(
          $command->organizationId,
          $command->roleId,
          $permissions,
        );

        $role = $this->roleRepository->findById($roleId);

        if (null === $role || (string) $role->organizationId() !== $command->organizationId) {
          throw OrganizationRoleNotFoundException::withId($command->roleId);
        }

        if ($role->isSystem()) {
          throw OrganizationSystemRoleImmutableException::cannotBeModified();
        }

        if (null !== $command->name) {
          $newName = new OrganizationRoleName($command->name);

          if (!$newName->equals($role->name())) {
            $existing = $this->roleRepository->findByOrganizationAndName($organizationId, $newName);

            if (null !== $existing) {
              throw OrganizationRoleNameAlreadyExistsException::create();
            }

            $role->rename($newName);
          }
        }

        $role->updatePermissions($permissions);

        if (null !== $command->description) {
          $role->updateDescription($command->description);
        }

        $this->roleRepository->save($role);

        return $role;
      },
    );

    $this->eventDispatcher->dispatch(new OrganizationRoleUpdatedEvent(
      organizationId: $command->organizationId,
      roleId: $command->roleId,
      roleName: (string) $role->name(),
      permissions: $role->permissions(),
    ));

    return new UpdateOrganizationRoleResult(
      id: (string) $role->id(),
      organizationId: (string) $role->organizationId(),
      name: (string) $role->name(),
      permissions: $role->permissions(),
      isSystem: $role->isSystem(),
      createdAt: $role->createdAt(),
      description: $role->description(),
    );
  }
  // #endregion
}
