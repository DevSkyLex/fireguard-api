<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Checklist\GetChecklist;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ChecklistItemResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChecklistItemResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $itemId,
    public string $label,
    public int $position,
    public bool $required,
    public ?string $description,
  ) {
  }
  // #endregion
}
