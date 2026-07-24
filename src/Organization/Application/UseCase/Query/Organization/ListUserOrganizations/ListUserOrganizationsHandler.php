<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListUserOrganizations;

use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort, PlanRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationCallerRoleResult, GetOrganizationResult};
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationStatus};
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
   * @param PlanRepositoryPort $planRepository the plan repository
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository
   */
  public function __construct(
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRepositoryPort $organizationRepository,
    private PlanRepositoryPort $planRepository,
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
    $membershipsByOrganizationId = [];
    foreach ($memberships as $membership) {
      if (!$membership->isActive()) {
        continue;
      }

      $id = (string) $membership->organizationId();
      $uniqueOrganizationIds[$id] = OrganizationId::fromString($id);
      $membershipsByOrganizationId[$id] ??= $membership;
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
      array_map(static fn ($organization): OrganizationId => $organization->id(), $organizations),
    );

    // GetOrganization resolves the plan (falling back to the default one) and
    // the list must agree with it: the organization switcher fills the active
    // organization from this list, so omitting the plan left the plan selector
    // unable to tell which plan is current. The catalog is small and fixed, so
    // one read beats a lookup per row.
    $plans = [];
    foreach ($this->planRepository->findAll() as $plan) {
      $plans[(string) $plan->id()] = $plan;
    }
    $defaultPlan = $this->planRepository->findDefault();

    $results = [];
    foreach ($organizations as $organization) {
      $organizationId = (string) $organization->id();
      $planId = $organization->planId();
      $plan = null !== $planId ? ($plans[(string) $planId] ?? null) : null;
      $plan ??= $defaultPlan;
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
        description: $organization->description(),
        logoUrl: $organization->logoUrl(),
        settings: $organization->settings(),
        country: null !== $organization->country() ? (string) $organization->country() : null,
        legalType: $organization->legalType()?->value,
        legalName: $organization->legalName(),
        registrationNumber: null !== $organization->registrationNumber() ? (string) $organization->registrationNumber() : null,
        vatNumber: null !== $organization->vatNumber() ? (string) $organization->vatNumber() : null,
        planId: $plan instanceof Plan ? (string) $plan->id() : null,
        planName: $plan instanceof Plan ? $plan->name() : null,
        isOwner: $organization->ownerUserId() === $query->userId,
        roles: $this->resolveCallerRoles($organization->id(), $membershipsByOrganizationId[$organizationId] ?? null),
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
   * Method resolveCallerRoles.
   *
   * Resolves the organization roles assigned to the caller's membership,
   * following the same repository join as ListOrganizationMembers /
   * GetCurrentOrganizationMemberProfile (`findRoleIdsForMember` then
   * `findByIdsInOrganization`). Returns an empty list when the caller has
   * no membership or no assigned role.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param ?OrganizationMember $membership the caller's active membership when known
   *
   * @return list<GetOrganizationCallerRoleResult> the caller's assigned roles
   */
  private function resolveCallerRoles(OrganizationId $organizationId, ?OrganizationMember $membership): array
  {
    if (null === $membership) {
      return [];
    }

    $roleIds = array_map(
      static fn (string $roleId): OrganizationRoleId => OrganizationRoleId::fromString($roleId),
      $this->memberRepository->findRoleIdsForMember($membership->id()),
    );

    if ([] === $roleIds) {
      return [];
    }

    $roles = [];
    foreach ($this->roleRepository->findByIdsInOrganization($organizationId, $roleIds) as $role) {
      $roles[] = new GetOrganizationCallerRoleResult(
        id: (string) $role->id(),
        label: (string) $role->name(),
      );
    }

    return $roles;
  }
  // #endregion
}
