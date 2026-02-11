<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationMembers;

use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListOrganizationMembersHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationMembersHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
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
   * @param ListOrganizationMembersQuery $query the query payload
   */
  public function __invoke(ListOrganizationMembersQuery $query): ListOrganizationMembersResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $members = $this->memberRepository->findByOrganizationId($organizationId);
    $results = [];

    foreach ($members as $member) {
      $results[] = new GetOrganizationMemberResult(
        id: (string) $member->id(),
        organizationId: (string) $member->organizationId(),
        userId: $member->userId(),
        isActive: $member->isActive(),
        joinedAt: $member->joinedAt(),
        roleIds: $this->memberRepository->findRoleIdsForMember($member->id()),
      );
    }

    return new ListOrganizationMembersResult($results);
  }
  // #endregion
}
