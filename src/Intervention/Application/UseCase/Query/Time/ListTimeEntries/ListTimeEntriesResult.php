<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Time\ListTimeEntries;

/**
 * ListTimeEntriesResult.
 *
 * @category Intervention
 *
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
   */
  public function __construct(public array $entries)
  {
  }
}
