<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Time\GetTimeEntry;

use Shared\Application\Message\QueryMessage;

/**
 * Class GetTimeEntryQuery
 *
 * Requests the bounded current view for one authorized journal entry.
 *
 * @category Query
 */
final readonly class GetTimeEntryQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the caller and owning task so a foreign entry remains hidden.
   *
   * @access public
   *
   * @param string $userId authenticated caller
   * @param string $taskId owning task identifier
   * @param string $entryId stable journal entry identifier
   *
   * @return void
   */
  public function __construct(public string $userId, public string $taskId, public string $entryId)
  {
  }
  // #endregion
}
