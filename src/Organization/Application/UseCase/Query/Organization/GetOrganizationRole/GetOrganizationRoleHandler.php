<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationRole;

use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId};
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetOrganizationRoleHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationRoleHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationRoleHandler class.
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
   * Resolves a single organization role, including its active member count.
   *
   * @since 1.0.0
   *
   * @param GetOrganizationRoleQuery $query the query payload
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationRoleNotFoundException when the role does not exist or
   *                                           does not belong to the
   *                                           organization
   *
   * @return GetOrganizationRoleResult the resolved role
   */
  public function __invoke(GetOrganizationRoleQuery $query): GetOrganizationRoleResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $roleId = OrganizationRoleId::fromString($query->roleId);
    $role = $this->roleRepository->findById($roleId);

    if (null === $role || (string) $role->organizationId() !== $query->organizationId) {
      throw OrganizationRoleNotFoundException::withId($query->roleId);
    }

    // Reuses the same grouped query ListOrganizationRolesHandler relies on —
    // there is no dedicated single-role count method on the port, and the
    // grouped query is already a single round trip.
    $memberCountsByRoleId = $this->memberRepository->countActiveMembersGroupedByRoleId($organizationId);

    return new GetOrganizationRoleResult(
      id: (string) $role->id(),
      organizationId: (string) $role->organizationId(),
      name: (string) $role->name(),
      permissions: $role->permissions(),
      isSystem: $role->isSystem(),
      createdAt: $role->createdAt(),
      description: $role->description(),
      memberCount: $memberCountsByRoleId[(string) $role->id()] ?? 0,
    );
  }
  // #endregion
}
