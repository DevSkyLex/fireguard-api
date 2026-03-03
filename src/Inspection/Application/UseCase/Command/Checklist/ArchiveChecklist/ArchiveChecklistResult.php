<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ArchiveChecklistResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ArchiveChecklistResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $checklistId,
    public string $status,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
