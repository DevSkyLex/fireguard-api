<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\UpdateChecklist;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateChecklistResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateChecklistResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $checklistId,
    public string $organizationId,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
