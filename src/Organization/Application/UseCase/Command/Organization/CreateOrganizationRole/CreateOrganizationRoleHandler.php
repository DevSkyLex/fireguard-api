<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\CreateOrganizationRole;

use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Event\Role\OrganizationRoleCreatedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationRoleNameAlreadyExistsException};
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\Exception\InvalidValueException;

use function array_unique;
use function array_values;
use function count;

/**
 * UseCase CreateOrganizationRoleHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateOrganizationRoleHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CreateOrganizationRoleHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository
   * @param UuidFactory $uuidFactory the UUID factory
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private UuidFactory $uuidFactory,
    private EventDispatcherPort $eventDispatcher,
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
   * @param CreateOrganizationRoleCommand $command the command payload
   */
  public function __invoke(CreateOrganizationRoleCommand $command): CreateOrganizationRoleResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $roleName = new OrganizationRoleName($command->name);
    $existing = $this->roleRepository->findByOrganizationAndName($organizationId, $roleName);
    if (null !== $existing) {
      throw OrganizationRoleNameAlreadyExistsException::create();
    }

    /** @var list<string> $permissions */
    $permissions = array_values(array_unique($command->permissions));

    if (0 === count($permissions)) {
      throw InvalidValueException::because('At least one permission is required.');
    }

    /** @var OrganizationRoleId $roleId */
    $roleId = $this->uuidFactory->create(OrganizationRoleId::class);

    $role = OrganizationRole::create(
      id: $roleId,
      organizationId: $organizationId,
      name: $roleName,
      permissions: $permissions,
      isSystem: $command->isSystem,
      description: $command->description ?? '',
    );

    $this->roleRepository->save($role);

    $this->eventDispatcher->dispatch(new OrganizationRoleCreatedEvent(
      organizationId: $command->organizationId,
      roleId: (string) $role->id(),
      roleName: (string) $role->name(),
      permissions: $role->permissions(),
    ));

    return new CreateOrganizationRoleResult(
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
