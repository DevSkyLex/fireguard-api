<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Plan\CreatePlan;

use Organization\Application\Port\Inbound\OrganizationQuotaCatalogPort;
use Organization\Application\Port\Outbound\Internal\PlanRepositoryPort;
use Organization\Domain\Exception\PlanKeyAlreadyExistsException;
use Organization\Domain\Model\Plan\{Plan, PlanCreationOptions};
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{TransactionManagerPort, UuidGeneratorPort};

/**
 * UseCase CreatePlanHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreatePlanHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreatePlanHandler class.
   *
   * @since 1.0.0
   *
   * @param PlanRepositoryPort $planRepository the plan repository port
   * @param UuidGeneratorPort $uuidFactory the UUID factory
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private PlanRepositoryPort $planRepository,
    private UuidGeneratorPort $uuidFactory,
    private TransactionManagerPort $transactionManager,
    private OrganizationQuotaCatalogPort $quotaCatalog,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Creates a new subscription plan in the catalog.
   *
   * @since 1.0.0
   *
   * @param CreatePlanCommand $command the command payload
   *
   * @return CreatePlanResult the use case result
   */
  public function __invoke(CreatePlanCommand $command): CreatePlanResult
  {
    $key = new PlanKey($command->key);

    if (null !== $this->planRepository->findByKey($key)) {
      throw PlanKeyAlreadyExistsException::withKey((string) $key);
    }

    /** @var PlanId $planId */
    $planId = new PlanId($this->uuidFactory->generate());

    $plan = Plan::create(
      id: $planId,
      key: $key,
      name: $command->name,
      limits: $this->quotaCatalog->normalizeLimits($command->limits),
      options: new PlanCreationOptions(
        description: $command->description,
        isActive: $command->isActive,
        isDefault: $command->isDefault,
        sortOrder: $command->sortOrder,
      ),
    );

    $this->transactionManager->transactional(function () use ($plan, $command): void {
      if ($command->isDefault) {
        $this->clearExistingDefault($plan->id());
      }

      $this->planRepository->save($plan);
    });

    return new CreatePlanResult(
      planId: (string) $planId,
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

    if (null !== $current && !$current->id()->equals($exceptId)) {
      $current->unmarkDefault();
      $this->planRepository->save($current);
    }
  }
  // #endregion
}
