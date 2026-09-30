<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

use DateTimeImmutable;

/**
 * The persisted schedule of a newly created recurrence.
 */
final readonly class InterventionRecurrenceSchedule
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Defines the validated timing configuration persisted for a recurrence.
   *
   * @access public
   *
   * @param string $frequency recurrence frequency
   * @param int $interval number of frequency units between occurrences
   * @param DateTimeImmutable $anchorDate date anchoring the recurrence schedule
   * @param string $timezone timezone used to calculate occurrences
   * @param int $leadTimeDays days of notice before an occurrence
   * @param DateTimeImmutable $nextOccurrenceAt next occurrence calculated from the schedule
   * @param ?DateTimeImmutable $endAt optional date after which occurrences stop
   *
   * @return void
   */
  public function __construct(
    public string $frequency,
    public int $interval,
    public DateTimeImmutable $anchorDate,
    public string $timezone,
    public int $leadTimeDays,
    public DateTimeImmutable $nextOccurrenceAt,
    public ?DateTimeImmutable $endAt,
  ) {
  }
  // #endregion
}
