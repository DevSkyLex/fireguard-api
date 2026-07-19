<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeleteOrganizationRole;

use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Event\Role\OrganizationRoleDeletedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

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
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private EventDispatcherPort $eventDispatcher,
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
    $role = $this->roleRepository->findById($roleId);

    if (null === $role || (string) $role->organizationId() !== (string) $organizationId) {
      throw OrganizationRoleNotFoundException::withId($command->roleId);
    }

    if ($role->isSystem()) {
      throw new InvalidArgumentException('System roles cannot be deleted.');
    }

    $roleName = (string) $role->name();

    $this->roleRepository->remove($role);

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
