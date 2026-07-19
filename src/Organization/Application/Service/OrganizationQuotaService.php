<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\Port\Outbound\{
  EquipmentStatisticsPort,
  FacilityStatisticsPort,
  InspectionStatisticsPort,
  OrganizationInvitationRepositoryPort,
  OrganizationMemberRepositoryPort,
  OrganizationQuotaLockPort,
  OrganizationRepositoryPort,
  PlanRepositoryPort
};
use Organization\Domain\Exception\OrganizationQuotaExceededException;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationQuotaResource};
use Symfony\Contracts\Service\ResetInterface;

/**
 * Service OrganizationQuotaService.
 *
 * Resolves the quantity caps defined by an organization's current plan, the
 * current usage of each capped resource, and enforces the caps at creation
 * time. Usage is sourced from the existing statistics ports so the Organization
 * module stays decoupled from the other modules' repositories.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationQuotaService implements OrganizationQuotaPort, ResetInterface
{
  /**
   * In-memory per-request cache of the resolved plan, keyed by organization id.
   * `false` marks a resolved-but-absent plan to avoid repeated lookups.
   *
   * @var array<string, Plan|false>
   */
  private array $planCache = [];

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationQuotaService class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param PlanRepositoryPort $planRepository the plan repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   * @param OrganizationInvitationRepositoryPort $invitationRepository the organization invitation repository
   * @param FacilityStatisticsPort $facilityStatistics the facility statistics port
   * @param EquipmentStatisticsPort $equipmentStatistics the equipment statistics port
   * @param InspectionStatisticsPort $inspectionStatistics the inspection statistics port
   * @param OrganizationQuotaLockPort $quotaLock the advisory lock serializing check+insert
   */
  public function __construct(
    private readonly OrganizationRepositoryPort $organizationRepository,
    private readonly PlanRepositoryPort $planRepository,
    private readonly OrganizationMemberRepositoryPort $memberRepository,
    private readonly OrganizationInvitationRepositoryPort $invitationRepository,
    private readonly FacilityStatisticsPort $facilityStatistics,
    private readonly EquipmentStatisticsPort $equipmentStatistics,
    private readonly InspectionStatisticsPort $inspectionStatistics,
    private readonly OrganizationQuotaLockPort $quotaLock,
  ) {
  }
  // #endregion

  // #region Methods
  public function getLimit(string $organizationId, OrganizationQuotaResource $resource): ?int
  {
    $plan = $this->resolvePlan($organizationId);

    return $plan instanceof Plan ? $plan->limitFor($resource) : null;
  }

  public function getUsage(string $organizationId, OrganizationQuotaResource $resource): int
  {
    // Usage counts the resources that occupy a plan slot: active members plus the
    // pending invitations that reserve one, and only the "active" (published and
    // not archived/decommissioned/cancelled) assets, so archiving, decommissioning
    // or cancelling frees capacity.
    return match ($resource) {
      OrganizationQuotaResource::MEMBERS => $this->countMemberUsage($organizationId),
      OrganizationQuotaResource::FACILITIES => $this->facilityStatistics->countActiveFacilities($organizationId),
      OrganizationQuotaResource::EQUIPMENT => $this->equipmentStatistics->countActiveEquipment($organizationId),
      OrganizationQuotaResource::INSPECTIONS => $this->inspectionStatistics->countActiveInspections($organizationId),
    };
  }

  public function assertCanAcceptMember(string $organizationId): void
  {
    $limit = $this->getLimit($organizationId, OrganizationQuotaResource::MEMBERS);

    if (null === $limit) {
      return;
    }

    // Serialize against concurrent member additions/invitations before counting,
    // so an acceptance running at the cap cannot slip past a simultaneous add.
    $this->quotaLock->acquire($organizationId, OrganizationQuotaResource::MEMBERS);

    // Count only active members: the pending invitation being accepted already
    // reserved a slot at emission time, so it must not block its own acceptance.
    $activeMembers = $this->memberRepository->countActiveByOrganizationId(
      OrganizationId::fromString($organizationId),
    );

    if ($activeMembers >= $limit) {
      throw OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::MEMBERS, $limit);
    }
  }

  public function assertCanAdd(string $organizationId, OrganizationQuotaResource $resource): void
  {
    $limit = $this->getLimit($organizationId, $resource);

    if (null === $limit) {
      return;
    }

    // Take the per-(organization, resource) advisory lock before counting so two
    // concurrent creators at limit-1 cannot both read an available slot and both
    // insert (TOCTOU). Requires an active transaction (see the port contract);
    // the lock is released when the caller's transaction commits or rolls back.
    $this->quotaLock->acquire($organizationId, $resource);

    if ($this->getUsage($organizationId, $resource) >= $limit) {
      throw OrganizationQuotaExceededException::forResource($resource, $limit);
    }
  }

  /**
   * @return list<array{resource: string, used: int, limit: int|null}>
   */
  public function getQuotaSummary(string $organizationId): array
  {
    $summary = [];

    foreach (OrganizationQuotaResource::cases() as $resource) {
      $summary[] = [
        'resource' => $resource->value,
        'used' => $this->getUsage($organizationId, $resource),
        'limit' => $this->getLimit($organizationId, $resource),
      ];
    }

    return $summary;
  }

  public function reset(): void
  {
    $this->planCache = [];
  }

  /**
   * Method countMemberUsage.
   *
   * Counts the member slots occupied by an organization: its active members
   * plus its pending invitations, each of which reserves a slot until it is
   * accepted, revoked, or expired.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return int the occupied member slots
   */
  private function countMemberUsage(string $organizationId): int
  {
    $organization = OrganizationId::fromString($organizationId);

    return $this->memberRepository->countActiveByOrganizationId($organization)
      + $this->invitationRepository->countPendingByOrganizationId($organization);
  }

  /**
   * Method resolvePlan.
   *
   * Resolves the organization's plan, falling back to the catalog default plan
   * when none is assigned. Caches the result per request.
   *
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
