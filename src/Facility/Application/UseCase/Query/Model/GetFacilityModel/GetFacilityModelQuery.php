<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Model\GetFacilityModel;

use Shared\Application\Message\QueryMessage;

/** UseCase GetFacilityModelQuery.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityModelQuery implements QueryMessage
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
   * @param string $userId the user id
   * @param string $modelId the model id
   *
   * @return void no return value
   */
  public function __construct(public string $userId, public string $modelId)
  {
  }
  // #endregion
}
