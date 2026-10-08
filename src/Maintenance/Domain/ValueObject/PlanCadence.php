<?php

declare(strict_types=1);

namespace Maintenance\Domain\ValueObject;

use DateInterval;
use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;

use function intdiv;
use function max;
use function min;
use function preg_match;
use function sprintf;

/**
 * Class PlanCadence
 *
 * An explicit calendar cadence retaining its original day after a short month.
 * Historical composite durations keep PHP's existing sliding calculation.
 *
 * @category ValueObject
 */
final readonly class PlanCadence
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Creation is restricted to the validated fixed and historical factories.
   *
   * @access private
   *
   * @param string $value the validated ISO duration
   * @param bool $legacy whether to preserve historical date arithmetic
   * @param int $step the positive count for a fixed cadence
   * @param string $unit its fixed calendar unit
   *
   * @return void
   */
  private function __construct(
    public string $value,
    public bool $legacy,
    private int $step,
    private string $unit,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromString
   *
   * Accepts a single positive day, week, month or year unit up to ten years.
   *
   * @access public
   *
   * @param string $value the ISO calendar duration
   *
   * @return self the validated fixed cadence
   */
  public static function fromString(string $value): self
  {
    if (1 !== preg_match('/^P([1-9]\d{0,3})([DWMY])$/D', $value, $matches)) {
      throw InvalidValueException::because('A plan cadence must use one positive day, week, month or year unit.');
    }

    $step = (int) $matches[1];
    $unit = $matches[2];
    $limit = match ($unit) {
      'D' => 3650,
      'W' => 520,
      'M' => 120,
      'Y' => 10,
    };

    if ($step > $limit) {
      throw InvalidValueException::because('A plan cadence must not exceed ten years.');
    }

    return new self($value, false, $step, $unit);
  }

  /**
   * Method legacyFromString
   *
   * Keeps the interval grammar and bounds of the historical inspection schedule.
   *
   * @access public
   *
   * @param string $value the historical ISO duration
   *
   * @return self the validated sliding cadence
   */
  public static function legacyFromString(string $value): self
  {
    PeriodicityInterval::fromString($value);

    return new self($value, true, 0, '');
  }

  /**
   * Method addTo
   *
   * Calculates the first date after an anchor; repeated fixed calculations must
   * use dateAt with the original anchor to preserve its day.
   *
   * @access public
   *
   * @param DateTimeImmutable $date the original fixed anchor or latest legacy completion
   *
   * @return DateTimeImmutable the following date
   */
  public function addTo(DateTimeImmutable $date): DateTimeImmutable
  {
    return $this->dateAt($date, 1);
  }

  /**
   * Method dateAt
   *
   * Finds a numbered date from an unchanged anchor, with month-end clamping.
   *
   * @access public
   *
   * @param DateTimeImmutable $anchor the first due date
   * @param int $index the non-negative cadence index, bounded to 100000
   *
   * @return DateTimeImmutable the due date at that index
   */
  public function dateAt(DateTimeImmutable $anchor, int $index): DateTimeImmutable
  {
    if ($index < 0 || $index > 100000) {
      throw InvalidValueException::because('A cadence index must be between zero and 100000.');
    }

    if ($this->legacy) {
      $date = $anchor;
      $interval = new DateInterval($this->value);
      for ($offset = 0; $offset < $index; ++$offset) {
        $date = $date->add($interval);
      }

      return $date;
    }

    $offset = $this->step * $index;

    if ('D' === $this->unit || 'W' === $this->unit) {
      return $anchor->add(new DateInterval(sprintf('P%dD', 'W' === $this->unit ? $offset * 7 : $offset)));
    }

    $year = (int) $anchor->format('Y');
    $month = (int) $anchor->format('n');
    if ('Y' === $this->unit) {
      $year += $offset;
    } else {
      $months = $year * 12 + $month - 1 + $offset;
      $year = intdiv($months, 12);
      $month = $months % 12 + 1;
    }

    $firstOfMonth = $anchor->setDate($year, $month, 1);

    return $firstOfMonth->setDate($year, $month, min((int) $anchor->format('j'), (int) $firstOfMonth->format('t')));
  }

  /**
   * Method nextAfter
   *
   * Skips missed fixed slots without producing retroactive occurrences.
   * Historical schedules instead slide from the supplied completion date.
   *
   * @access public
   *
   * @param DateTimeImmutable $anchor the original first due date
   * @param DateTimeImmutable $date the instant to advance beyond
   *
   * @return DateTimeImmutable the next due date strictly after the instant
   */
  public function nextAfter(DateTimeImmutable $anchor, DateTimeImmutable $date): DateTimeImmutable
  {
    if ($this->legacy) {
      return $date->add(new DateInterval($this->value));
    }

    if ($date < $anchor) {
      return $anchor;
    }

    $inAnchorZone = $date->setTimezone($anchor->getTimezone());
    $elapsed = match ($this->unit) {
      'D' => (int) $anchor->diff($inAnchorZone)->days,
      'W' => intdiv((int) $anchor->diff($inAnchorZone)->days, 7),
      'M' => ((int) $inAnchorZone->format('Y') - (int) $anchor->format('Y')) * 12 + (int) $inAnchorZone->format('n') - (int) $anchor->format('n'),
      'Y' => (int) $inAnchorZone->format('Y') - (int) $anchor->format('Y'),
      default => throw InvalidValueException::because('A fixed cadence needs a calendar unit.'),
    };
    $index = max(0, intdiv($elapsed, $this->step));
    $next = $this->dateAt($anchor, $index);

    while ($next <= $date) {
      $next = $this->dateAt($anchor, ++$index);
    }

    return $next;
  }

  /**
   * Method preview
   *
   * Includes the first slot on or after the requested date. A legacy preview
   * begins at the stored due date and slides each following slot.
   *
   * @access public
   *
   * @param DateTimeImmutable $anchor the original first due date
   * @param DateTimeImmutable $from the current due date or lower preview bound
   * @param int $count the number of dates, from one to 100
   *
   * @return list<DateTimeImmutable> the preview dates
   */
  public function preview(DateTimeImmutable $anchor, DateTimeImmutable $from, int $count = 3): array
  {
    if ($count < 1 || $count > 100) {
      throw InvalidValueException::because('A cadence preview must contain between one and 100 dates.');
    }

    $dates = [];
    $date = $this->legacy ? $from : $this->nextAfter($anchor, $from->modify('-1 microsecond'));
    for ($index = 0; $index < $count; ++$index) {
      $dates[] = $date;
      $date = $this->nextAfter($anchor, $date);
    }

    return $dates;
  }
  // #endregion
}
