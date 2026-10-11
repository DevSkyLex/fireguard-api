<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ChangeSelfServiceOrganizationPlan;

use Organization\Application\Contract\Exception\OrganizationAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\Internal\PlanRepositoryPort;
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\{ChangeOrganizationPlanCommand, ChangeOrganizationPlanResult};
use Organization\Domain\Exception\{PaidOrganizationPlanRequiresBillingException, PlanNotFoundException};
use Organization\Domain\ValueObject\PlanId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Handler ChangeSelfServiceOrganizationPlanHandler.
 *
 * Keeps self-service entitlement separate from the trusted Billing assignment path.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChangeSelfServiceOrganizationPlanHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the contextual permission capability
   * @param PlanRepositoryPort $plans the owner's plan catalog
   * @param CommandBusPort $commands the internal validated plan mutation
   */
  public function __construct(private OrganizationAuthorizationPort $authorization, private PlanRepositoryPort $plans, private CommandBusPort $commands)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ChangeSelfServiceOrganizationPlanCommand $command the authenticated self-service request
   *
   * @return ChangeOrganizationPlanResult the committed assignment
   */
  public function __invoke(ChangeSelfServiceOrganizationPlanCommand $command): ChangeOrganizationPlanResult
  {
    if (!$this->authorization->hasPermission($command->actorUserId, $command->organizationId, 'organization.settings.write')) {
      throw OrganizationAccessDeniedException::missingPermission('organization.settings.write');
    }
    $plan = $this->plans->findById(PlanId::fromString($command->planId));
    if (null === $plan) {
      throw new PlanNotFoundException('Plan not found.');
    }
    if (!$plan->isDefault()) {
      throw new PaidOrganizationPlanRequiresBillingException();
    }

    /** @var ChangeOrganizationPlanResult */
    return $this->commands->dispatch(new ChangeOrganizationPlanCommand($command->organizationId, $command->planId, $command->acknowledgeOveruse));
  }
  // #endregion
}
