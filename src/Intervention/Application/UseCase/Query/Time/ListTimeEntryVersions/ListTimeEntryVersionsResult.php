<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Time\ListTimeEntryVersions;

use Intervention\Application\Contract\Time\TimeEntryVersionView;
use Shared\Application\Message\ResultMessage;

/**
 * Class ListTimeEntryVersionsResult
 *
 * Carries a bounded history window and its continuation metadata.
 *
 * @category Result
 */
final readonly class ListTimeEntryVersionsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Records the complete retained count independently of the returned window.
   *
   * @access public
   *
   * @param list<TimeEntryVersionView> $versions newest-first retained revisions
   * @param int $totalItems complete retained count
   * @param int $itemsPerPage requested page size
   * @param ?int $nextBeforeRevision exclusive continuation cursor, null at the end
   *
   * @return void
   */
  public function __construct(public array $versions, public int $totalItems, public int $itemsPerPage, public ?int $nextBeforeRevision)
  {
  }
  // #endregion
}
