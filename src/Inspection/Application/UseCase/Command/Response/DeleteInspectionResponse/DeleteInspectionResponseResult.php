<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Response\DeleteInspectionResponse;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteInspectionResponseResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInspectionResponseResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $responseId,
    public string $inspectionId,
  ) {
  }
  // #endregion
}
