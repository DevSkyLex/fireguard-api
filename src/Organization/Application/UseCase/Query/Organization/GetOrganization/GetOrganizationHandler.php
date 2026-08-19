<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganization;

use Organization\Application\Port\Inbound\OrganizationCallerMembershipPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetOrganizationHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   * @param PlanRepositoryPort $planRepository the plan repository
   * @param OrganizationCallerMembershipPort $callerMembership the caller-membership projection port (isOwner/roles)
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private PlanRepositoryPort $planRepository,
    private OrganizationCallerMembershipPort $callerMembership,
  ) {
  }
  // #endregion

  // #region Methods

  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution. When the query carries a
   * `callerUserId`, `isOwner`/`roles` are resolved through
   * {@see OrganizationCallerMembershipPort} — the same projection
   * `ListUserOrganizationsHandler` uses — costing one extra indexed
   * membership lookup plus, when the caller holds roles, the role-name
   * join. Both stay null when no `callerUserId` is provided, unchanged from
   * every other pre-existing caller of this query.
   *
   * @since 1.0.0
   *
   * @param GetOrganizationQuery $query the query payload
   */
  public function __invoke(GetOrganizationQuery $query): GetOrganizationResult
  {
    $organization = $this->organizationRepository->findById(OrganizationId::fromString($query->organizationId));

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $planId = $organization->planId();
    $plan = null !== $planId ? $this->planRepository->findById($planId) : null;
    $plan ??= $this->planRepository->findDefault();

    $isOwner = null;
    $roles = null;
    if (null !== $query->callerUserId) {
      $isOwner = $this->callerMembership->isOwner($organization->ownerUserId(), $query->callerUserId);
      $membership = $this->callerMembership->findActiveCallerMembership($organization->id(), $query->callerUserId);
      $roles = $this->callerMembership->resolveRoles($organization->id(), $membership);
    }

    return new GetOrganizationResult(
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
      description: $organization->description(),
      logoUrl: $organization->logoUrl(),
      settings: $organization->settings(),
      planId: $plan instanceof Plan ? (string) $plan->id() : null,
      planName: $plan instanceof Plan ? $plan->name() : null,
      country: null !== $organization->country() ? (string) $organization->country() : null,
      legalType: $organization->legalType()?->value,
      legalName: $organization->legalName(),
      registrationNumber: null !== $organization->registrationNumber() ? (string) $organization->registrationNumber() : null,
      vatNumber: null !== $organization->vatNumber() ? (string) $organization->vatNumber() : null,
      isOwner: $isOwner,
      roles: $roles,
    );
  }
  // #endregion
}
