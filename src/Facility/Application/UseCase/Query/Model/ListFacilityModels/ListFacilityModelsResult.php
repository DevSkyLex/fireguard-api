<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Model\ListFacilityModels;

use Facility\Application\Contract\Model\FacilityModelView;
use Shared\Application\Message\ResultMessage;

/** UseCase ListFacilityModelsResult.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFacilityModelsResult implements ResultMessage
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
   * @param list<FacilityModelView> $models
   *
   * @return void no return value
   */
  public function __construct(public array $models)
  {
  }
  // #endregion
}
