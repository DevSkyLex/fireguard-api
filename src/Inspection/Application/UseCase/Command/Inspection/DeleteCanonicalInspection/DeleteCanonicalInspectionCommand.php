<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\DeleteCanonicalInspection;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteCanonicalInspectionCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCanonicalInspectionCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $inspectionId,
    public int $expectedRevision,
  ) {
  }
  // #endregion
}
