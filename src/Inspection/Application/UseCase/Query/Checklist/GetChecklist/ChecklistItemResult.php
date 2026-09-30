<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Checklist\GetChecklist;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ChecklistItemResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChecklistItemResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Projects the label, position, required state, and optional description of a checklist item.
   *
   * @access public
   *
   * @param string $itemId identifier of the checklist item
   * @param string $label prompt shown for the checklist item
   * @param int $position display order within the checklist
   * @param bool $required whether an answer is required
   * @param ?string $description optional additional guidance for the item
   *
   * @return void
   */
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
