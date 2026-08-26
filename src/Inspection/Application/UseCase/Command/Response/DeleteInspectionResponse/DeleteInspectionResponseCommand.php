<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Response\DeleteInspectionResponse;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteInspectionResponseCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInspectionResponseCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $responseId,
    public int $expectedRevision,
  ) {
  }
  // #endregion
}
