<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Model\UploadFacilityModel;

use Shared\Application\Message\CommandMessage;

/** UseCase UploadFacilityModelCommand.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UploadFacilityModelCommand implements CommandMessage
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
   * @param string $organizationId the organization id
   * @param string $buildingId the building id
   * @param string $fileName the file name
   * @param string $contents the contents
   *
   * @return void no return value
   */
  public function __construct(public string $userId, public string $organizationId, public string $buildingId, public string $fileName, public string $contents)
  {
  }
  // #endregion
}
