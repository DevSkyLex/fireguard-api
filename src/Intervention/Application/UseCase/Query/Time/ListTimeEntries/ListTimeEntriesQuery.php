<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Time\ListTimeEntries;

use InvalidArgumentException;

use function intdiv;

use const PHP_INT_MAX;

/**
 * ListTimeEntriesQuery.
 *
 * @category Intervention
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTimeEntriesQuery implements \Shared\Application\Message\QueryMessage
{
  /**
   * @since 1.0.0
   *
   * @param string $userId authenticated account identifier used for authorization
   * @param string $taskId intervention work-item identifier
   * @param int $page one-based journal page
   * @param int $itemsPerPage maximum entries returned, from 1 to 100
   */
  public function __construct(public string $userId, public string $taskId, public int $page = 1, public int $itemsPerPage = 30)
  {
    if ($page < 1 || $itemsPerPage < 1 || $itemsPerPage > 100 || $page > intdiv(PHP_INT_MAX, $itemsPerPage)) {
      throw new InvalidArgumentException('Invalid time journal pagination.');
    }
  }
}
