<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Plan\DeletePlan;

use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Domain\Exception\{DefaultPlanCannotBeDeletedException, PlanNotFoundException};
use Organization\Domain\ValueObject\PlanId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * UseCase DeletePlanHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeletePlanHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeletePlanHandler class.
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
   * Deletes a plan from the catalog. The default plan cannot be deleted.
   *
   * @since 1.0.0
   *
   * @param DeletePlanCommand $command the command payload
   *
   * @return DeletePlanResult the use case result
   */
  public function __invoke(DeletePlanCommand $command): DeletePlanResult
  {
    $planId = PlanId::fromString($command->planId);
    $plan = $this->planRepository->findById($planId);

    if (null === $plan) {
      throw PlanNotFoundException::withId($command->planId);
    }

    if ($plan->isDefault()) {
      throw DefaultPlanCannotBeDeletedException::create();
    }

    $this->transactionManager->transactional(function () use ($planId): void {
      $this->planRepository->delete($planId);
    });

    return new DeletePlanResult(
      planId: (string) $planId,
    );
  }
  // #endregion
}
