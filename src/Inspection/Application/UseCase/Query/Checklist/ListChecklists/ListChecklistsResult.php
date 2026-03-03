<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Checklist\ListChecklists;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListChecklistsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChecklistsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<\Inspection\Application\UseCase\Query\Checklist\GetChecklist\GetChecklistResult> $checklists the checklist list
   */
  public function __construct(
    public array $checklists,
  ) {
  }
  // #endregion
}
