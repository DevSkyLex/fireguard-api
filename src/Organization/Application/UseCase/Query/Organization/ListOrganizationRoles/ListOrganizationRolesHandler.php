<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationRoles;

use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListOrganizationRolesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationRolesHandler implements QueryHandler
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
   * @param ListOrganizationRolesQuery $query the query payload
   */
  public function __invoke(ListOrganizationRolesQuery $query): ListOrganizationRolesResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $roles = $this->roleRepository->findByOrganizationId($organizationId);
    $results = [];

    foreach ($roles as $role) {
      $results[] = new GetOrganizationRoleResult(
        id: (string) $role->id(),
        organizationId: (string) $role->organizationId(),
        name: (string) $role->name(),
        permissions: $role->permissions(),
        isSystem: $role->isSystem(),
        createdAt: $role->createdAt(),
      );
    }

    return new ListOrganizationRolesResult($results);
  }
  // #endregion
}
