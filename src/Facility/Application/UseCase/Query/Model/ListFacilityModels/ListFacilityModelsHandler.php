<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Model\ListFacilityModels;

use Facility\Application\Port\Outbound\FacilityModelRepositoryPort;
use Facility\Application\Service\FacilityModelAccessGuard;
use Shared\Application\Message\QueryHandler;

/** UseCase ListFacilityModelsHandler.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFacilityModelsHandler implements QueryHandler
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
   * @param ListFacilityModelsQuery $query the query
   *
   * @return ListFacilityModelsResult the operation result
   */
  public function __invoke(ListFacilityModelsQuery $query): ListFacilityModelsResult
  {
    $this->access->building($query->userId, $query->organizationId, $query->buildingId, false);
    $models = $this->models->findByBuilding($query->organizationId, $query->buildingId);

    return new ListFacilityModelsResult($this->access->views($models));
  }
  // #endregion
}
