<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationMembers;

use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;

use function count;

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
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationMembersHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   */
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
   *
   * @return PaginatedResult<GetOrganizationMemberResult>
   */
  public function __invoke(ListOrganizationMembersQuery $query): PaginatedResult
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

    $total = count($results);

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $total,
      offset: 0,
    );
  }
  // #endregion
}
