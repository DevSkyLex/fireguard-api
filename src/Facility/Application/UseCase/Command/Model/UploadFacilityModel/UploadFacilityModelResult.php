<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Model\UploadFacilityModel;

use Facility\Application\Contract\Model\FacilityModelView;
use Shared\Application\Message\ResultMessage;

/** UseCase UploadFacilityModelResult.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UploadFacilityModelResult implements ResultMessage
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
   * @param FacilityModelView $model the model
   *
   * @return void no return value
   */
  public function __construct(public FacilityModelView $model)
  {
  }
  // #endregion
}
