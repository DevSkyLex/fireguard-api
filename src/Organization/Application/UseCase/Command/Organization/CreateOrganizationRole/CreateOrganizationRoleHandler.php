<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\CreateOrganizationRole;

use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;

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
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private UuidFactory $uuidFactory,
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
      throw new InvalidArgumentException('Role name already exists for this organization.');
    }

    /** @var list<string> $permissions */
    $permissions = array_values(array_unique($command->permissions));

    if (0 === count($permissions)) {
      throw new InvalidArgumentException('At least one permission is required.');
    }

    /** @var OrganizationRoleId $roleId */
    $roleId = $this->uuidFactory->create(OrganizationRoleId::class);

    $role = OrganizationRole::create(
      id: $roleId,
      organizationId: $organizationId,
      name: $roleName,
      permissions: $permissions,
      isSystem: $command->isSystem,
    );

    $this->roleRepository->save($role);

    return new CreateOrganizationRoleResult(
      id: (string) $role->id(),
      organizationId: (string) $role->organizationId(),
      name: (string) $role->name(),
      permissions: $role->permissions(),
      isSystem: $role->isSystem(),
      createdAt: $role->createdAt(),
    );
  }
  // #endregion
}
