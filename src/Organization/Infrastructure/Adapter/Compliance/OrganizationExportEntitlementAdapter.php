<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Compliance;

use Compliance\Application\Port\Outbound\ComplianceExportEntitlementPort;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Domain\Exception\InvalidValueException;

use function in_array;

/**
 * Adapter OrganizationExportEntitlementAdapter.
 *
 * Implements the Compliance module's export entitlement port by resolving
 * the organization's current plan with the SAME logic as
 * `OrganizationQuotaService::resolvePlan()` (assigned plan, falling back to
 * the catalog default), then checking the plan key against a small
 * allow-list. Plans are quota-only with no feature-flag concept, so this
 * allow-list is a deliberate policy decision: if plan keys are renamed or
 * added, this adapter must be updated.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationExportEntitlementAdapter implements ComplianceExportEntitlementPort
{
  // #region Constants
  /**
   * Constant ENTITLED_PLAN_KEYS.
   *
   * Plan keys entitled to the regulatory safety register export.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  private const array ENTITLED_PLAN_KEYS = ['pro', 'max'];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param PlanRepositoryPort $planRepository the plan repository port
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private PlanRepositoryPort $planRepository,
  ) {
  }
  // #endregion

  // #region Methods
  public function isExportEntitled(string $organizationId): bool
  {
    $planKey = $this->resolvePlanKey($organizationId);

    return null !== $planKey && in_array($planKey, self::ENTITLED_PLAN_KEYS, true);
  }

  public function resolvePlanKey(string $organizationId): ?string
  {
    try {
      $organization = $this->organizationRepository->findById(OrganizationId::fromString($organizationId));
    } catch (InvalidValueException) {
      return null;
    }

    if (null === $organization) {
      return null;
    }

    $planId = $organization->planId();
    $plan = null !== $planId ? $this->planRepository->findById($planId) : null;
    $plan ??= $this->planRepository->findDefault();

    return $plan?->key()->__toString();
  }
  // #endregion
}
