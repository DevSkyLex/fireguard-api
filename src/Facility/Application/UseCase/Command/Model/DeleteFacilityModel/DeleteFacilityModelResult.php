<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Model\DeleteFacilityModel;

use Shared\Application\Message\ResultMessage;

/** UseCase DeleteFacilityModelResult.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteFacilityModelResult implements ResultMessage
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
   * @param string $id the id
   *
   * @return void no return value
   */
  public function __construct(public string $id)
  {
  }
  // #endregion
}
