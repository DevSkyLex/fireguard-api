<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationMembers;

use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId};
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

use function sprintf;

/**
 * UseCase ListOrganizationMembersHandler.
 *
 * @category UseCase
 *
 * @version 1.1.0
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
   * Handles the corresponding use case execution. Filtering (search, status,
   * role), sorting and pagination are all pushed down to the repository —
   * SQL-level, not in-memory.
   *
   * @since 1.1.0
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

    $isActive = $this->resolveStatusFilter($query->status);
    $roleId = null !== $query->roleId ? OrganizationRoleId::fromString($query->roleId) : null;

    $ownerUserId = $organization->ownerUserId();

    $members = $this->memberRepository->findByOrganizationId(
      $organizationId,
      $query->search,
      $isActive,
      $roleId,
      $query->sorting,
      $query->pagination->limit,
      $query->pagination->offset,
    );

    $total = $this->memberRepository->countByOrganizationId(
      $organizationId,
      $query->search,
      $isActive,
      $roleId,
    );

    $results = [];
    foreach ($members as $member) {
      $results[] = new GetOrganizationMemberResult(
        id: (string) $member->id(),
        organizationId: (string) $member->organizationId(),
        userId: $member->userId(),
        isActive: $member->isActive(),
        joinedAt: $member->joinedAt(),
        roleIds: $this->memberRepository->findRoleIdsForMember($member->id()),
        isOwner: $member->userId() === $ownerUserId,
      );
    }

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );
  }

  /**
   * Method resolveStatusFilter.
   *
   * Translates the `active`/`inactive`/`all` (or null) status filter into
   * the boolean the repository filters on.
   *
   * @since 1.1.0
   *
   * @param ?string $status the raw status filter
   *
   * @return ?bool the activation filter, or null for no filter
   */
  private function resolveStatusFilter(?string $status): ?bool
  {
    return match ($status) {
      null, 'all' => null,
      'active' => true,
      'inactive' => false,
      default => throw InvalidValueException::because(sprintf('Invalid member status filter "%s".', $status)),
    };
  }
  // #endregion
}
