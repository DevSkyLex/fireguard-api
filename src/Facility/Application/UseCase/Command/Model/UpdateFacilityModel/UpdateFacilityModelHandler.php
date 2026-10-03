<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Model\UpdateFacilityModel;

use Facility\Application\Port\Outbound\FacilityModelRepositoryPort;
use Facility\Application\Service\FacilityModelAccessGuard;
use Facility\Domain\Model\FacilityModel\FacilityModel;
use Facility\Domain\ValueObject\FacilityModelTransform;
use Shared\Application\Message\CommandHandler;

use function in_array;
use function is_array;
use function is_int;

/**
 * UseCase UpdateFacilityModelHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateFacilityModelHandler implements CommandHandler
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
   * @param UpdateFacilityModelCommand $command the command
   *
   * @return UpdateFacilityModelResult the operation result
   */
  public function __invoke(UpdateFacilityModelCommand $command): UpdateFacilityModelResult
  {
    $model = $this->access->model($command->userId, $command->modelId, true);
    $this->access->revision($model, $command->expectedRevision);
    $previousBindings = $model->bindings;
    $allowed = $this->access->bindingTargets($model);
    $bindings = $this->retainUnavailableBindings($model, $command->bindings, $command->removeBindingNodeIndices, $allowed);
    $model->changeSettings(FacilityModelTransform::fromArray($command->transform), $bindings, $command->removeBindingNodeIndices);
    $this->access->bindings($model, $model->bindings, $previousBindings, $allowed);
    $this->models->update($model, $command->expectedRevision);

    return new UpdateFacilityModelResult($this->access->view($model, $allowed));
  }

  /**
   * Method retainUnavailableBindings.
   *
   * Keeps masked historical references until their node is explicitly removed or reassigned.
   *
   * @access private
   * @since 1.0.0
   *
   * @param FacilityModel $model the stored model
   * @param ?list<mixed> $requestedBindings the optional replacement of usable associations
   * @param list<mixed> $removedIndices the explicitly removed nodes
   * @param array<string, true> $allowed the current published subtree
   *
   * @return list<mixed> the associations to validate and persist
   */
  private function retainUnavailableBindings(FacilityModel $model, ?array $requestedBindings, array $removedIndices, array $allowed): array
  {
    if ([] === $requestedBindings) {
      return [];
    }
    $bindings = $requestedBindings ?? [];
    $assigned = [];
    foreach ($bindings as $binding) {
      if (is_array($binding) && is_int($binding['nodeIndex'] ?? null)) {
        $assigned[$binding['nodeIndex']] = true;
      }
    }
    foreach ($model->bindings as $binding) {
      if (!isset($assigned[$binding['nodeIndex']]) && !in_array($binding['nodeIndex'], $removedIndices, true)
        && (null === $requestedBindings || !isset($allowed[$binding['facilityId']]))) {
        $bindings[] = $binding;
      }
    }

    return $bindings;
  }
  // #endregion
}
