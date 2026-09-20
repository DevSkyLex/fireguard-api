<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Time;

/**
 * TimeEntryVersionView.
 *
 * @category Intervention
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TimeEntryVersionView
{
  /**
   * @since 1.0.0
   *
   * @param int $revision revision of this resource, used independently of other aggregates
   * @param string $workedOn organization-local calendar date on which the work was performed
   * @param int $minutes duration in whole minutes
   * @param ?string $note optional context supplied with the time entry
   * @param bool $cancelled whether the entry is cancelled while its history remains retained
   * @param string $actorId member who authored the operation, not necessarily its beneficiary
   * @param string $recordedAt timestamp when this version was recorded
   */
  public function __construct(public int $revision, public string $workedOn, public int $minutes, public ?string $note, public bool $cancelled, public string $actorId, public string $recordedAt)
  {
  }
}
