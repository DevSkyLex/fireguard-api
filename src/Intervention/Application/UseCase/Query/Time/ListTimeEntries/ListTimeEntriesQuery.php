<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Time\ListTimeEntries;

/**
 * ListTimeEntriesQuery.
 *
 * @category Intervention
 *
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
   */
  public function __construct(public string $userId, public string $taskId)
  {
  }
}
