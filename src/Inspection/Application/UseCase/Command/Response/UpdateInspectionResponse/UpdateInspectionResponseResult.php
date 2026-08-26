<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Response\UpdateInspectionResponse;

use Inspection\Application\Contract\Response\InspectionResponseView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateInspectionResponseResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateInspectionResponseResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public InspectionResponseView $view,
  ) {
  }
  // #endregion
}
