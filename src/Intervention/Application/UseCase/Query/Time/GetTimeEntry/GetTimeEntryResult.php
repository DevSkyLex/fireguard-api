<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Time\GetTimeEntry;

use Intervention\Application\Contract\Time\TimeEntryView;
use Shared\Application\Message\ResultMessage;

/**
 * Class GetTimeEntryResult
 *
 * Carries current journal values independently of journal size or history length.
 *
 * @category Result
 */
final readonly class GetTimeEntryResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Exposes one authorized current entry with a bounded inline version.
   *
   * @access public
   *
   * @param TimeEntryView $entry current values and history continuation
   *
   * @return void
   */
  public function __construct(public TimeEntryView $entry)
  {
  }
  // #endregion
}
