<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Time\ListTimeEntries;

/**
 * ListTimeEntriesResult.
 *
 * @category Intervention
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTimeEntriesResult implements \Shared\Application\Message\ResultMessage
{
  /**
   * @since 1.0.0
   *
   * @param list<\Intervention\Application\Contract\Time\TimeEntryView> $entries
   * @param int $totalItems exact scoped journal count
   * @param int $page one-based requested page
   * @param int $itemsPerPage maximum returned entries
   */
  public function __construct(public array $entries, public int $totalItems, public int $page, public int $itemsPerPage)
  {
  }
}
