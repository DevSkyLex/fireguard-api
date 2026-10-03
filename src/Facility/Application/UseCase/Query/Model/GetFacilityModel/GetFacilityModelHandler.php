<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Model\GetFacilityModel;

use Facility\Application\Service\FacilityModelAccessGuard;
use Shared\Application\Message\QueryHandler;

/** UseCase GetFacilityModelHandler.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityModelHandler implements QueryHandler
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
   *
   * @return void no return value
   */
  public function __construct(private FacilityModelAccessGuard $access)
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
   * @param GetFacilityModelQuery $query the query
   *
   * @return GetFacilityModelResult the operation result
   */
  public function __invoke(GetFacilityModelQuery $query): GetFacilityModelResult
  {
    $model = $this->access->model($query->userId, $query->modelId, false);

    return new GetFacilityModelResult($this->access->view($model));
  }
  // #endregion
}
