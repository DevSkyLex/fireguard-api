<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Response\CreateInspectionResponse;

use Inspection\Application\Contract\Response\InspectionResponseView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateInspectionResponseResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInspectionResponseResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public InspectionResponseView $view,
  ) {
  }
  // #endregion
}
