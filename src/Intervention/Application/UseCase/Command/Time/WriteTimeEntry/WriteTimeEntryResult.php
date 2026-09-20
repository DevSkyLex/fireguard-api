<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Time\WriteTimeEntry;

/**
 * WriteTimeEntryResult.
 *
 * @category Intervention
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WriteTimeEntryResult implements \Shared\Application\Message\ResultMessage
{
  /**
   * @since 1.0.0
   *
   * @param \Intervention\Application\Contract\Time\TimeEntryView $entry independent time-entry aggregate or view
   */
  public function __construct(public \Intervention\Application\Contract\Time\TimeEntryView $entry)
  {
  }
}
