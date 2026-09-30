<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Response\UpdateInspectionResponse;

use Inspection\Application\Contract\Response\InspectionResponseView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateInspectionResponseResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateInspectionResponseResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the response view assembled after the answer update.
   *
   * @access public
   *
   * @param InspectionResponseView $view updated checklist response projection returned by the use case
   *
   * @return void
   */
  public function __construct(
    public InspectionResponseView $view,
  ) {
  }
  // #endregion
}
