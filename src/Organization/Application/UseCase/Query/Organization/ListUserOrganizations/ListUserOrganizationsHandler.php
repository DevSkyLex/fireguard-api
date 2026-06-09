<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListUserOrganizations;

use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationStatus};
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use ValueError;

use function array_map;
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
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListUserOrganizationsHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   */
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
    try {
      $status = null !== $query->status ? OrganizationStatus::from($query->status)->value : null;
    } catch (ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

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
      return new PaginatedResult(
        items: [],
        total: 0,
        limit: $query->pagination->limit,
        offset: $query->pagination->offset,
      );
    }

    $organizationIds = array_values($uniqueOrganizationIds);

    $organizations = $this->organizationRepository->findByIds(
      $organizationIds,
      $status,
      $query->search,
      $query->sorting,
      $query->pagination->limit,
      $query->pagination->offset,
    );

    $total = $this->organizationRepository->countByIds(
      $organizationIds,
      $status,
      $query->search,
    );

    $memberCounts = $this->memberRepository->countByOrganizationIds(
      array_values(array_map(static fn ($organization): OrganizationId => $organization->id(), $organizations)),
    );

    $results = [];
    foreach ($organizations as $organization) {
      $organizationId = (string) $organization->id();
      $results[] = new GetOrganizationResult(
        id: $organizationId,
        name: (string) $organization->name(),
        slug: (string) $organization->slug(),
        ownerUserId: $organization->ownerUserId(),
        createdByUserId: $organization->createdByUserId(),
        status: $organization->status()->value,
        isActive: $organization->isActive(),
        createdAt: $organization->createdAt(),
        updatedAt: $organization->updatedAt(),
        memberCount: $memberCounts[$organizationId] ?? 0,
      );
    }

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );
  }
  // #endregion
}
