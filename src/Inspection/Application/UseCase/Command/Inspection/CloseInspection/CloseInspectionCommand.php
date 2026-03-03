<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CloseInspection;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CloseInspectionCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CloseInspectionCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
  ) {
  }
  // #endregion
}
