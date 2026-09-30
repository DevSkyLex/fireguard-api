<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

use DateTimeImmutable;

/**
 * Schedule overrides, including the recomputed next occurrence.
 */
final readonly class InterventionRecurrenceSchedulePatch
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries schedule overrides and their field-presence information.
   *
   * @access public
   *
   * @param ?string $timezone replacement timezone, when supplied
   * @param ?int $leadTimeDays replacement notice period in days, when supplied
   * @param ?DateTimeImmutable $nextOccurrenceAt recomputed next occurrence, when supplied
   * @param bool $hasTimezone whether timezone was included in the patch
   * @param bool $hasLeadTimeDays whether notice period was included
   * @param bool $hasNextOccurrenceAt whether next occurrence was included
   *
   * @return void
   */
  public function __construct(
    public ?string $timezone,
    public ?int $leadTimeDays,
    public ?DateTimeImmutable $nextOccurrenceAt,
    public bool $hasTimezone,
    public bool $hasLeadTimeDays,
    public bool $hasNextOccurrenceAt,
  ) {
  }
  // #endregion
}
