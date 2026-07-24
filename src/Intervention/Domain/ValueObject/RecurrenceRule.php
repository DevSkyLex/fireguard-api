<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Intervention\Domain\Exception\InterventionValidationException;

use function sprintf;

/**
 * ValueObject RecurrenceRule.
 *
 * A recurring intervention schedule expressed as a fixed cadence
 * ({@see RecurrenceFrequency}) multiplied by an interval count (e.g. "every
 * 2 months"), anchored to a reference date. Persisted as discrete columns
 * (`frequency`, `interval_count`, `anchor_date`) on `intervention_recurrences`
 * — never as a freeform rrule string; the table's `rrule` column is reserved
 * for a future expression syntax and this value object never reads it.
 *
 * `nextAfter()` walks forward from the anchor date by repeatedly adding one
 * step of the rule, evaluated in the recurrence's own IANA timezone, until
 * the cursor lands strictly after the given instant.
 *
 * **End-of-month behavior.** The month-based frequencies (`monthly`,
 * `quarterly`, `semiannual`, `annual`) add calendar months through PHP's
 * native `DateInterval`, which does **not** clamp end-of-month overflow:
 * adding one month to January 31st, 2026 overflows into March 3rd (February
 * 2026 has only 28 days, so the extra 3 days spill into March) rather than
 * clamping to February 28th. Because each step is added onto the *previous*
 * cursor position — not recomputed from the anchor day every time — once an
 * overflow happens the occurrence day permanently drifts: the next step is
 * added from the drifted date (March 3rd), not the original anchor day
 * (the 31st), so the following occurrence becomes April 3rd, not April
 * 30th/May 1st. This is accepted, documented behavior (see
 * `RecurrenceRuleTest` for the exact drift), not a bug: an end-of-month
 * anchor on a short-interval monthly/quarterly recurrence should be expected
 * to wander after the first short month it crosses.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RecurrenceRule
{
  // #region Constants
  /**
   * Inclusive lower bound for the interval count.
   */
  public const int MIN_INTERVAL = 1;

  /**
   * Inclusive upper bound for the interval count.
   */
  public const int MAX_INTERVAL = 12;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param RecurrenceFrequency $frequency the base cadence
   * @param int $interval the interval count multiplying the base cadence, between 1 and 12
   * @param DateTimeImmutable $anchorDate the reference date the recurrence is walked from
   */
  public function __construct(
    public RecurrenceFrequency $frequency,
    public int $interval,
    public DateTimeImmutable $anchorDate,
  ) {
    if ($this->interval < self::MIN_INTERVAL || $this->interval > self::MAX_INTERVAL) {
      throw new InterventionValidationException(sprintf(
        'The recurrence interval must be between %d and %d.',
        self::MIN_INTERVAL,
        self::MAX_INTERVAL,
      ));
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method fromValues.
   *
   * @static
   *
   * Builds a rule from raw (already-persisted or user-submitted) primitive
   * values, validating the frequency string and the interval bounds.
   *
   * @since 1.0.0
   *
   * @param string $frequency the raw frequency value
   * @param int $interval the interval count value
   * @param DateTimeImmutable $anchorDate the anchor date value
   *
   * @return self the validated rule
   */
  public static function fromValues(string $frequency, int $interval, DateTimeImmutable $anchorDate): self
  {
    $case = RecurrenceFrequency::tryFrom($frequency);
    if (!$case instanceof RecurrenceFrequency) {
      throw new InterventionValidationException(sprintf('Unknown recurrence frequency "%s".', $frequency));
    }

    return new self($case, $interval, $anchorDate);
  }

  /**
   * Method assertTimezone.
   *
   * @static
   *
   * Validates an IANA timezone identifier, translating a malformed value
   * into a domain validation exception.
   *
   * @since 1.0.0
   *
   * @param string $timezone the IANA timezone identifier
   *
   * @return DateTimeZone the validated timezone
   */
  public static function assertTimezone(string $timezone): DateTimeZone
  {
    try {
      return new DateTimeZone($timezone);
    } catch (Exception $exception) {
      throw new InterventionValidationException(sprintf('"%s" is not a valid IANA timezone.', $timezone), 0, $exception);
    }
  }

  /**
   * Method nextAfter.
   *
   * Computes the next occurrence strictly after `$from`, walking from the
   * anchor date in the recurrence's own timezone. See the class docblock for
   * the documented end-of-month overflow/drift behavior of the month-based
   * frequencies.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $from the instant the next occurrence must fall strictly after
   * @param string $timezone the IANA timezone the rule is evaluated in
   *
   * @return DateTimeImmutable the next occurrence, in UTC
   */
  public function nextAfter(DateTimeImmutable $from, string $timezone): DateTimeImmutable
  {
    $zone = self::assertTimezone($timezone);
    $cursor = new DateTimeImmutable($this->anchorDate->format('Y-m-d') . ' 00:00:00', $zone);
    $boundary = $from->setTimezone($zone);
    $step = $this->stepInterval();

    while ($cursor <= $boundary) {
      $cursor = $cursor->add($step);
    }

    return $cursor->setTimezone(new DateTimeZone('UTC'));
  }

  /**
   * Method stepInterval.
   *
   * Derives the calendar step added at each `nextAfter()` iteration: whole
   * weeks for `weekly`, whole calendar months for every other frequency
   * (multiplied by the frequency's month count and the interval).
   *
   * @since 1.0.0
   *
   * @return DateInterval the step interval
   */
  private function stepInterval(): DateInterval
  {
    return match ($this->frequency) {
      RecurrenceFrequency::WEEKLY => new DateInterval('P' . ($this->interval * 7) . 'D'),
      RecurrenceFrequency::MONTHLY => new DateInterval('P' . $this->interval . 'M'),
      RecurrenceFrequency::QUARTERLY => new DateInterval('P' . ($this->interval * 3) . 'M'),
      RecurrenceFrequency::SEMIANNUAL => new DateInterval('P' . ($this->interval * 6) . 'M'),
      RecurrenceFrequency::ANNUAL => new DateInterval('P' . ($this->interval * 12) . 'M'),
    };
  }
  // #endregion
}
