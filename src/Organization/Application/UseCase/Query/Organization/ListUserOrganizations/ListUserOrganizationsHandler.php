<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListUserOrganizations;

use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

use function array_values;

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
   */
  public function __invoke(ListUserOrganizationsQuery $query): ListUserOrganizationsResult
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

    if ([] === $uniqueOrganizationIds) {
      return new ListUserOrganizationsResult([]);
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
      );
    }

    return new ListUserOrganizationsResult($results);
  }
  // #endregion
}
