<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Billing;

use Billing\Application\Contract\Plan\PlanSummary;
use Billing\Application\Port\Outbound\OrganizationPlanPort;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Adapter OrganizationPlanAdapter.
 *
 * Implements the Billing module's plan-lookup port by resolving the
 * organization's currently assigned plan with the SAME logic as
 * `OrganizationQuotaService::resolvePlan()` and
 * `OrganizationExportEntitlementAdapter::resolvePlanKey()`: the explicit
 * assignment, falling back to the catalog default plan.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationPlanAdapter implements OrganizationPlanPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationPlanAdapter class.
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
  /**
   * {@inheritDoc}
   */
  public function findCurrentPlan(string $organizationId): ?PlanSummary
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

    return null !== $plan ? new PlanSummary(key: (string) $plan->key(), name: $plan->name()) : null;
  }
  // #endregion
}
