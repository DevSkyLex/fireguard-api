<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Model\DeleteFacilityModel;

use Facility\Application\Port\Outbound\Internal\FacilityModelRepositoryPort;
use Facility\Application\Service\FacilityModelAccessGuard;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{FileCleanupPort, TransactionManagerPort};

/**
 * UseCase DeleteFacilityModelHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteFacilityModelHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Initializes the model capability with its typed dependencies and state.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModelAccessGuard $access the access
   * @param FacilityModelRepositoryPort $models the models
   * @param FileCleanupPort $cleanup records deferred deletion
   *
   * @return void no return value
   */
  public function __construct(private FacilityModelAccessGuard $access, private FacilityModelRepositoryPort $models, private FileCleanupPort $cleanup, private TransactionManagerPort $transactionManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Executes the requested operation and returns its typed result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param DeleteFacilityModelCommand $command the command
   *
   * @return DeleteFacilityModelResult the operation result
   */
  public function __invoke(DeleteFacilityModelCommand $command): DeleteFacilityModelResult
  {
    $model = $this->access->model($command->userId, $command->modelId, true);
    $this->access->revision($model, $command->expectedRevision);
    $this->transactionManager->transactional(function () use ($model, $command): void {
      $this->models->delete($model, $command->expectedRevision);
      $this->cleanup->request($model->storagePath);
    });

    return new DeleteFacilityModelResult($model->id);
  }
  // #endregion
}
