<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Contract\Quota\{OrganizationQuotaExceededException, OrganizationQuotaResource};
use Organization\Application\Port\Inbound\{OrganizationQuotaCatalogPort, OrganizationQuotaPort};
use Organization\Application\Port\Outbound\Internal\{OrganizationMemberRepositoryPort, OrganizationQuotaLockPort, OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\OrganizationId;

/**
 * Class OrganizationQuotaService
 *
 * Resolves organization plan caps, resource usage and quota enforcement through module ports.
 *
 * Resolves the quantity caps defined by an organization's current plan, the
 * current usage of each capped resource, and enforces the caps at creation
 * time. Usage is sourced from the existing statistics ports so the Organization
 * module stays decoupled from the other modules' repositories.
 *
 * The public surface speaks the contract types
 * (`Application\Contract\Quota`): every method takes the contract resource
 * enum and throws the contract quota-exceeded exception, so sibling modules
 * never import `Organization\Domain`. Each contract case is mapped to its
 * Domain counterpart here before the plan, statistics, and lock ports are
 * consulted.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationQuotaService implements OrganizationQuotaPort
{
  /**
   * Property planCache
   *
   * Caches each organization's resolved plan for this request; false marks an absent plan.
   *
   * @access private
   *
   * @var array<string, Plan|false>
   */
  private array $planCache = [];

  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies plan, usage, invitation and transaction-scoped quota-lock ports.
   *
   * @access public
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param PlanRepositoryPort $planRepository the plan repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   * @param OrganizationQuotaLockPort $quotaLock the advisory lock serializing check+insert
   *
   * @return void
   */
  public function __construct(
    private readonly OrganizationRepositoryPort $organizationRepository,
    private readonly PlanRepositoryPort $planRepository,
    private readonly OrganizationMemberRepositoryPort $memberRepository,
    private readonly OrganizationQuotaLockPort $quotaLock,
    private readonly OrganizationQuotaCatalogPort $catalog,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method getLimit
   *
   * Resolves the plan cap for a resource, returning null when the plan has no cap.
   *
   * @access public
   *
   * @param string $organizationId organization whose plan is read
   * @param OrganizationQuotaResource|string $resource resource whose cap is requested
   *
   * @return int|null configured limit, or null when unlimited
   */
  public function getLimit(string $organizationId, OrganizationQuotaResource|string $resource): ?int
  {
    return $this->limitFor($organizationId, $this->resourceKey($resource));
  }

  /**
   * Method getUsage
   *
   * Returns the current quota usage for the organization and resource.
   *
   * @access public
   *
   * @param string $organizationId organization whose resources are counted
   * @param OrganizationQuotaResource|string $resource resource to count
   *
   * @return int current occupied resource slots
   */
  public function getUsage(string $organizationId, OrganizationQuotaResource|string $resource): int
  {
    return $this->usageFor($organizationId, $this->resourceKey($resource));
  }

  /**
   * Method assertCanAcceptMember
   *
   * Checks the active member cap while holding its transaction-scoped advisory lock.
   * The caller must run this inside the transaction that creates the membership.
   *
   * @access public
   *
   * @param string $organizationId organization receiving the member
   *
   * @return void
   *
   * @throws OrganizationQuotaExceededException when the active member cap is reached
   */
  public function assertCanAcceptMember(string $organizationId): void
  {
    $this->catalog->get('members');
    $limit = $this->limitFor($organizationId, 'members');

    if (null === $limit) {
      return;
    }

    // Serialize against concurrent member additions/invitations before counting,
    // so an acceptance running at the cap cannot slip past a simultaneous add.
    $this->quotaLock->acquire($organizationId, 'members');

    // Count only active members: the pending invitation being accepted already
    // reserved a slot at emission time, so it must not block its own acceptance.
    $activeMembers = $this->memberRepository->countActiveByOrganizationId(
      OrganizationId::fromString($organizationId),
    );

    if ($activeMembers >= $limit) {
      throw OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::MEMBERS->value, $limit);
    }
  }

  /**
   * Method assertCanAdd
   *
   * Checks capacity for one resource while holding a transaction-scoped advisory lock.
   * The caller must run this inside the transaction that inserts the resource.
   *
   * @access public
   *
   * @param string $organizationId organization receiving the resource
   * @param OrganizationQuotaResource|string $resource resource to add
   *
   * @return void
   *
   * @throws OrganizationQuotaExceededException when the plan cap has been reached
   */
  public function assertCanAdd(string $organizationId, OrganizationQuotaResource|string $resource): void
  {
    $domainResource = $this->resourceKey($resource);
    $limit = $this->limitFor($organizationId, $domainResource);

    if (null === $limit) {
      return;
    }

    // Take the per-(organization, resource) advisory lock before counting so two
    // concurrent creators at limit-1 cannot both read an available slot and both
    // insert (TOCTOU). Requires an active transaction (see the port contract);
    // the lock is released when the caller's transaction commits or rolls back.
    $this->quotaLock->acquire($organizationId, $domainResource);

    if ($this->usageFor($organizationId, $domainResource) >= $limit) {
      throw OrganizationQuotaExceededException::forResource($domainResource, $limit);
    }
  }

  /**
   * Method assertCanAddMultiple
   *
   * Checks capacity for a batch while holding the same lock used for single additions.
   * The caller must run this inside the transaction that inserts the batch.
   *
   * @access public
   *
   * @param string $organizationId organization receiving the resources
   * @param OrganizationQuotaResource|string $resource resource to add
   * @param int $count number to add; non-positive values are a no-op
   *
   * @return void
   *
   * @throws OrganizationQuotaExceededException when the batch would exceed the plan cap
   */
  public function assertCanAddMultiple(string $organizationId, OrganizationQuotaResource|string $resource, int $count): void
  {
    $this->resourceKey($resource);
    if ($count <= 0) {
      return;
    }

    $domainResource = $this->resourceKey($resource);
    $limit = $this->limitFor($organizationId, $domainResource);

    if (null === $limit) {
      return;
    }

    // Same advisory lock as assertCanAdd(): taken before counting so a
    // concurrent single or batched add cannot slip past this check between
    // the count and the inserts it guards.
    $this->quotaLock->acquire($organizationId, $domainResource);

    if ($this->usageFor($organizationId, $domainResource) + $count > $limit) {
      throw OrganizationQuotaExceededException::forResource($domainResource, $limit);
    }
  }

  /**
   * Method assertProjectedCanAdd
   *
   * Projects the quota after provisional additions without acquiring a lock or reserving capacity.
   * This result is advisory and may become stale before a later write.
   *
   * @access public
   *
   * @param string $organizationId organization whose quota is projected
   * @param OrganizationQuotaResource|string $resource resource to add
   * @param int $additionalOffset provisional resources already counted in the same batch
   *
   * @return void
   *
   * @throws OrganizationQuotaExceededException when the projected count reaches the cap
   */
  public function assertProjectedCanAdd(string $organizationId, OrganizationQuotaResource|string $resource, int $additionalOffset = 0): void
  {
    $domainResource = $this->resourceKey($resource);
    $limit = $this->limitFor($organizationId, $domainResource);

    if (null === $limit) {
      return;
    }

    // Deliberately no advisory lock: this is a projection for a caller (a
    // dry-run batch) that persists nothing and therefore has no insert to
    // serialize against — see the port contract.
    if ($this->usageFor($organizationId, $domainResource) + $additionalOffset >= $limit) {
      throw OrganizationQuotaExceededException::forResource($domainResource, $limit);
    }
  }

  /**
   * Method getQuotaSummary
   *
   * Returns current usage and plan limits for each resource in the contract enum.
   *
   * @access public
   *
   * @param string $organizationId organization whose quotas are summarized
   *
   * @return list<array{resource: string, used: int, limit: int|null}>
   */
  public function getQuotaSummary(string $organizationId): array
  {
    $summary = [];

    foreach ($this->catalog->definitions() as $definition) {
      $resource = $definition->key;
      $summary[] = [
        'resource' => $resource,
        'used' => $this->usageFor($organizationId, $resource),
        'limit' => $this->limitFor($organizationId, $resource),
      ];
    }

    return $summary;
  }

  /**
   * Method reset
   *
   * Clears the per-request plan cache so the next read resolves current plan state.
   *
   * @access public
   *
   * @return void
   */
  public function reset(): void
  {
    $this->planCache = [];
  }

  /**
   * Method toDomainResource
   *
   * Maps a contract resource case to its Domain counterpart — the single
   * point where the contract surface meets the Domain enum the plan, lock,
   * and statistics internals are typed with.
   *
   * @access private
   * @since 1.0.0
   *
   * @param OrganizationQuotaResource|string $resource the contract resource
   *
   * @return string the registered resource key
   */
  private function resourceKey(OrganizationQuotaResource|string $resource): string
  {
    $key = $resource instanceof OrganizationQuotaResource ? $resource->value : $resource;
    $this->catalog->get($key);

    return $key;
  }

  /**
   * Method limitFor
   *
   * Returns the cap for a resource under the organization's plan, or null
   * when the resource is unlimited.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $resource the resource to look up
   *
   * @return ?int the cap, or null when unlimited
   */
  private function limitFor(string $organizationId, string $resource): ?int
  {
    $plan = $this->resolvePlan($organizationId);

    return $plan instanceof Plan ? $plan->limitFor($resource) : null;
  }

  /**
   * Method usageFor
   *
   * Returns the current quantity of a resource owned by the organization.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $resource the resource to count
   *
   * @return int the current usage
   */
  private function usageFor(string $organizationId, string $resource): int
  {
    return $this->catalog->usage($organizationId, $resource);
  }

  /**
   * Method resolvePlan
   *
   * Resolves the organization's plan, falling back to the catalog default plan
   * when none is assigned. Caches the result per request.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return ?Plan the resolved plan, or null when none is configured
   */
  private function resolvePlan(string $organizationId): ?Plan
  {
    if (isset($this->planCache[$organizationId])) {
      $cached = $this->planCache[$organizationId];

      return $cached instanceof Plan ? $cached : null;
    }

    $organization = $this->organizationRepository->findById(OrganizationId::fromString($organizationId));

    $plan = null;
    if (null !== $organization) {
      $planId = $organization->planId();
      $plan = null !== $planId ? $this->planRepository->findById($planId) : null;
      $plan ??= $this->planRepository->findDefault();
    }

    $this->planCache[$organizationId] = $plan ?? false;

    return $plan;
  }
  // #endregion
}
