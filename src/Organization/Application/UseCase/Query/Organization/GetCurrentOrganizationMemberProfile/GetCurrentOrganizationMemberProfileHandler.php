<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetCurrentOrganizationMemberProfile;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{
  OrganizationMemberRepositoryPort,
  OrganizationRepositoryPort,
  OrganizationRoleRepositoryPort
};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId};
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * UseCase GetCurrentOrganizationMemberProfileHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCurrentOrganizationMemberProfileHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetCurrentOrganizationMemberProfileHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private OrganizationAuthorizationPort $authorization,
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
   * @param GetCurrentOrganizationMemberProfileQuery $query the query payload
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationMemberNotFoundException when the user has no active membership
   */
  public function __invoke(GetCurrentOrganizationMemberProfileQuery $query): GetCurrentOrganizationMemberProfileResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $member = $this->memberRepository->findByOrganizationAndUser($organizationId, $query->userId);

    if (null === $member || !$member->isActive()) {
      throw OrganizationMemberNotFoundException::forUserInOrganization($query->userId, $query->organizationId);
    }

    $roleIds = array_map(
      static fn (string $roleId): OrganizationRoleId => OrganizationRoleId::fromString($roleId),
      $this->memberRepository->findRoleIdsForMember($member->id()),
    );
    $roles = $this->roleRepository->findByIdsInOrganization($organizationId, $roleIds);

    $roleResults = [];
    foreach ($roles as $role) {
      $roleResults[] = new GetCurrentOrganizationMemberProfileRoleResult(
        id: (string) $role->id(),
        organizationId: (string) $role->organizationId(),
        name: (string) $role->name(),
        permissions: $role->permissions(),
        isSystem: $role->isSystem(),
        createdAt: $role->createdAt(),
        description: $role->description(),
      );
    }

    return new GetCurrentOrganizationMemberProfileResult(
      id: (string) $member->id(),
      organizationId: (string) $member->organizationId(),
      userId: $member->userId(),
      isActive: $member->isActive(),
      joinedAt: $member->joinedAt(),
      roles: $roleResults,
      permissions: $this->authorization->getUserPermissions($query->userId, $query->organizationId),
    );
  }
  // #endregion
}
