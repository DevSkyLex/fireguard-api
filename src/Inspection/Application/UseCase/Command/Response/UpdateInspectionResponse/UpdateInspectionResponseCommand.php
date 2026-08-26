<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Response\UpdateInspectionResponse;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateInspectionResponseCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateInspectionResponseCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $responseId,
    public int $expectedRevision,
    public mixed $value = null,
  ) {
  }
  // #endregion
}
