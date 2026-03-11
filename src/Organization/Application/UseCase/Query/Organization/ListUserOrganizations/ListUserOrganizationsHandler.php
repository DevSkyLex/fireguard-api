<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListUserOrganizations;

use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;

use function array_values;
use function count;

/**
 * UseCase ListUserOrganizationsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUserOrganizationsHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRepositoryPort $organizationRepository,
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
   * @param ListUserOrganizationsQuery $query the query payload
   *
   * @return PaginatedResult<GetOrganizationResult>
   */
  public function __invoke(ListUserOrganizationsQuery $query): PaginatedResult
  {
    $memberships = $this->memberRepository->findByUserId($query->userId);

    $uniqueOrganizationIds = [];
    foreach ($memberships as $membership) {
      if (!$membership->isActive()) {
        continue;
      }

      $id = (string) $membership->organizationId();
      $uniqueOrganizationIds[$id] = OrganizationId::fromString($id);
    }

    $total = count($uniqueOrganizationIds);

    if (0 === $total) {
      return new PaginatedResult(
        items: [],
        total: 0,
        limit: $query->pagination->limit,
        offset: $query->pagination->offset,
      );
    }

    $organizations = $this->organizationRepository->findByIds(array_values($uniqueOrganizationIds));

    $results = [];
    foreach ($organizations as $organization) {
      $results[] = new GetOrganizationResult(
        id: (string) $organization->id(),
        name: (string) $organization->name(),
        slug: (string) $organization->slug(),
        ownerUserId: $organization->ownerUserId(),
        createdByUserId: $organization->createdByUserId(),
        status: $organization->status()->value,
        isActive: $organization->isActive(),
        createdAt: $organization->createdAt(),
        updatedAt: $organization->updatedAt(),
        memberCount: $this->memberRepository->countByOrganizationId($organization->id()),
      );
    }

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $total,
      offset: 0,
    );
  }
  // #endregion
}
