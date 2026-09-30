<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Response\DeleteInspectionResponse;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteInspectionResponseResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInspectionResponseResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the response and inspection identifiers after deletion.
   *
   * @access public
   *
   * @param string $responseId identifier of the deleted checklist response
   * @param string $inspectionId inspection that contained the response
   *
   * @return void
   */
  public function __construct(
    public string $responseId,
    public string $inspectionId,
  ) {
  }
  // #endregion
}
