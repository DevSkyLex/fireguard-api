<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\SubmitInspection;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase SubmitInspectionCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SubmitInspectionCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
  ) {
  }
  // #endregion
}
