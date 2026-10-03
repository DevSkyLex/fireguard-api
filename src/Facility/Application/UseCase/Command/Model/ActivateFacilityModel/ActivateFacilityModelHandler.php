<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Model\ActivateFacilityModel;

use Facility\Application\Port\Outbound\FacilityModelRepositoryPort;
use Facility\Application\Service\FacilityModelAccessGuard;
use Shared\Application\Message\CommandHandler;

/**
 * UseCase ActivateFacilityModelHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ActivateFacilityModelHandler implements CommandHandler
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
   *
   * @return void no return value
   */
  public function __construct(private FacilityModelAccessGuard $access, private FacilityModelRepositoryPort $models)
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
   * @param ActivateFacilityModelCommand $command the command
   *
   * @return ActivateFacilityModelResult the operation result
   */
  public function __invoke(ActivateFacilityModelCommand $command): ActivateFacilityModelResult
  {
    $model = $this->access->model($command->userId, $command->modelId, true);
    $this->access->revision($model, $command->expectedRevision);
    $allowed = $this->access->bindingTargets($model);
    $this->access->bindings($model, $model->bindings, [], $allowed);
    $model->activate();
    $this->models->activate($model, $command->expectedRevision);

    return new ActivateFacilityModelResult($this->access->view($model, $allowed));
  }
  // #endregion
}
