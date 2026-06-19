<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan;

use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Domain\Exception\{OrganizationNotFoundException, PlanNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, PlanId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * UseCase ChangeOrganizationPlanHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChangeOrganizationPlanHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ChangeOrganizationPlanHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param PlanRepositoryPort $planRepository the plan repository port
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private PlanRepositoryPort $planRepository,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Assigns the organization to the requested subscription plan.
   *
   * @since 1.0.0
   *
   * @param ChangeOrganizationPlanCommand $command the command payload
   *
   * @return ChangeOrganizationPlanResult the use case result
   */
  public function __invoke(ChangeOrganizationPlanCommand $command): ChangeOrganizationPlanResult
  {
    $organization = $this->organizationRepository->findById(OrganizationId::fromString($command->organizationId));

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $planId = PlanId::fromString($command->planId);
    $plan = $this->planRepository->findById($planId);

    if (null === $plan) {
      throw PlanNotFoundException::withId($command->planId);
    }

    if (!$plan->isActive()) {
      throw new InvalidArgumentException('The selected plan is not available.');
    }

    $organization->changePlan($planId);

    $this->transactionManager->transactional(function () use ($organization): void {
      $this->organizationRepository->save($organization);
    });

    return new ChangeOrganizationPlanResult(
      organizationId: (string) $organization->id(),
      planId: (string) $planId,
    );
  }
  // #endregion
}
