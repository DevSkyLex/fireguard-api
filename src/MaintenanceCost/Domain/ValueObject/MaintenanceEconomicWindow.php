<?php

declare(strict_types=1);

namespace MaintenanceCost\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;

/**
 * Class MaintenanceEconomicWindow
 *
 * Validates inclusive UTC calendar dates before any financial source query.
 *
 * @category ValueObject
 */
final readonly class MaintenanceEconomicWindow
{
  // #region Constructor
  /**
   * Constructor
   *
   * @access private
   *
   * @param DateTimeImmutable $from inclusive UTC start
   * @param DateTimeImmutable $to exclusive UTC end
   */
  private function __construct(public DateTimeImmutable $from, public DateTimeImmutable $to)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromDates
   *
   * @access public
   *
   * @param string $from inclusive YYYY-MM-DD
   * @param string $to inclusive YYYY-MM-DD
   *
   * @return self bounded report window of at most 366 calendar days
   */
  public static function fromDates(string $from, string $to): self
  {
    $start = self::date($from);
    $last = self::date($to);
    if ($last < $start || $start->diff($last)->days >= 366) {
      throw MaintenanceCostException::invalid('Use an ordered report window of at most 366 calendar days.');
    }

    return new self($start, $last->modify('+1 day'));
  }

  /**
   * Method date
   *
   * @access private
   *
   * @param string $value strict calendar date
   *
   * @return DateTimeImmutable UTC day without normalization of invalid dates
   */
  private static function date(string $value): DateTimeImmutable
  {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
    if (false === $date || $date->format('Y-m-d') !== $value) {
      throw MaintenanceCostException::invalid('Report dates must use valid YYYY-MM-DD calendar dates.');
    }

    return $date;
  }
  // #endregion
}
