<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Model\DownloadFacilityModel;

use Shared\Application\Message\ResultMessage;

/** UseCase DownloadFacilityModelResult.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DownloadFacilityModelResult implements ResultMessage
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
   * @param string $contents the contents
   * @param string $fileName the file name
   *
   * @return void no return value
   */
  public function __construct(public string $contents, public string $fileName)
  {
  }
  // #endregion
}
