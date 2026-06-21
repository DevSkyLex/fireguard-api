<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Billing;

use Billing\Application\Port\Outbound\OrganizationPlanAssignmentPort;
use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\ChangeOrganizationPlanCommand;
use Organization\Domain\Exception\PlanNotFoundException;
use Organization\Domain\ValueObject\PlanKey;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Adapter OrganizationPlanAssignmentAdapter.
 *
 * Implements the Billing module's plan-assignment port using the Organization
 * module's own change-plan use case. Resolves the stable plan key to its
 * identifier and dispatches {@see ChangeOrganizationPlanCommand}, so the paid
 * plan is applied through the same validated path as self-service changes.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationPlanAssignmentAdapter implements OrganizationPlanAssignmentPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationPlanAssignmentAdapter class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param PlanRepositoryPort $planRepository the plan repository
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private PlanRepositoryPort $planRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function assignPlanByKey(string $organizationId, string $planKey): void
  {
    $plan = $this->planRepository->findByKey(new PlanKey($planKey));

    if (null === $plan) {
      throw PlanNotFoundException::withKey($planKey);
    }

    $this->commandBus->dispatch(new ChangeOrganizationPlanCommand(
      organizationId: $organizationId,
      planId: (string) $plan->id(),
    ));
  }
  // #endregion
}
