<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationRoles;

use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListOrganizationRolesHandler.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationRolesHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationRolesHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
  ) {
  }
  // #endregion

  // #region Methods

  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution. When the query carries no
   * pagination, every role is returned (see {@see ListOrganizationRolesQuery}
   * for why that is the default) — `$total` is still computed so a caller
   * that does paginate can build a proper page count.
   *
   * @since 1.1.0
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

    $roles = $this->roleRepository->findByOrganizationId(
      $organizationId,
      $query->pagination?->limit,
      $query->pagination?->offset,
    );
    $total = $this->roleRepository->countByOrganizationId($organizationId);
    $memberCountsByRoleId = $this->memberRepository->countActiveMembersGroupedByRoleId($organizationId);
    $results = [];

    foreach ($roles as $role) {
      $roleId = (string) $role->id();
      $results[] = new GetOrganizationRoleResult(
        id: $roleId,
        organizationId: (string) $role->organizationId(),
        name: (string) $role->name(),
        permissions: $role->permissions(),
        isSystem: $role->isSystem(),
        createdAt: $role->createdAt(),
        description: $role->description(),
        memberCount: $memberCountsByRoleId[$roleId] ?? 0,
      );
    }

    return new ListOrganizationRolesResult($results, $total);
  }
  // #endregion
}
