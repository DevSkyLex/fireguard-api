<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Plan\UpdatePlan;

use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Domain\Exception\PlanNotFoundException;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\PlanId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * UseCase UpdatePlanHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdatePlanHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdatePlanHandler class.
   *
   * @since 1.0.0
   *
   * @param PlanRepositoryPort $planRepository the plan repository port
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private PlanRepositoryPort $planRepository,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Applies the requested changes to an existing plan.
   *
   * @since 1.0.0
   *
   * @param UpdatePlanCommand $command the command payload
   *
   * @return UpdatePlanResult the use case result
   */
  public function __invoke(UpdatePlanCommand $command): UpdatePlanResult
  {
    $plan = $this->planRepository->findById(PlanId::fromString($command->planId));

    if (null === $plan) {
      throw PlanNotFoundException::withId($command->planId);
    }

    if (null !== $command->name || null !== $command->description) {
      $plan->rename(
        $command->name ?? $plan->name(),
        $command->description ?? $plan->description(),
      );
    }

    if (null !== $command->limits) {
      $plan->changeLimits($command->limits);
    }

    if (null !== $command->isActive) {
      $command->isActive ? $plan->activate() : $plan->deactivate();
    }

    if (null !== $command->sortOrder) {
      $plan->changeSortOrder($command->sortOrder);
    }

    if (true === $command->isDefault) {
      $plan->markDefault();
    } elseif (false === $command->isDefault) {
      $plan->unmarkDefault();
    }

    $this->transactionManager->transactional(function () use ($plan, $command): void {
      if (true === $command->isDefault) {
        $this->clearExistingDefault($plan->id());
      }

      $this->planRepository->save($plan);
    });

    return new UpdatePlanResult(
      planId: (string) $plan->id(),
    );
  }

  /**
   * Method clearExistingDefault.
   *
   * Removes the default flag from the current default plan when it differs from
   * the provided plan, preserving a single catalog default.
   *
   * @since 1.0.0
   *
   * @param PlanId $exceptId the plan identifier to keep as default
   */
  private function clearExistingDefault(PlanId $exceptId): void
  {
    $current = $this->planRepository->findDefault();

    if ($current instanceof Plan && !$current->id()->equals($exceptId)) {
      $current->unmarkDefault();
      $this->planRepository->save($current);
    }
  }
  // #endregion
}
