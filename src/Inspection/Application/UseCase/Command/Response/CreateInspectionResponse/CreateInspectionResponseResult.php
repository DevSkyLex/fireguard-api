<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Response\CreateInspectionResponse;

use Inspection\Application\Contract\Response\InspectionResponseView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateInspectionResponseResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInspectionResponseResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the response view assembled after the checklist answer is saved.
   *
   * @access public
   *
   * @param InspectionResponseView $view saved checklist response projection returned by the use case
   *
   * @return void
   */
  public function __construct(
    public InspectionResponseView $view,
  ) {
  }
  // #endregion
}
