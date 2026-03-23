<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\UpdateOrganizationRole;

use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId};
use Shared\Application\Message\CommandHandler;

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
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
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
    $role = $this->roleRepository->findById($roleId);

    if (null === $role || (string) $role->organizationId() !== $command->organizationId) {
      throw new InvalidArgumentException('Role not found in this organization.');
    }

    if ($role->isSystem()) {
      throw new InvalidArgumentException('System roles cannot be modified.');
    }

    /** @var list<string> $permissions */
    $permissions = array_values(array_unique($command->permissions));

    if (0 === count($permissions)) {
      throw new InvalidArgumentException('At least one permission is required.');
    }

    $role->updatePermissions($permissions);

    if (null !== $command->description) {
      $role->updateDescription($command->description);
    }

    $this->roleRepository->save($role);

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
