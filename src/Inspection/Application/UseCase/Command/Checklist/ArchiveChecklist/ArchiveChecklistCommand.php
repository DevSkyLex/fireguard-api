<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ArchiveChecklistCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ArchiveChecklistCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $checklistId,
  ) {
  }
  // #endregion
}
