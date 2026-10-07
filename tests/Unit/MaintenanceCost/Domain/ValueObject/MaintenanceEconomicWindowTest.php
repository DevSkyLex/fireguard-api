<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Domain\ValueObject;

use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Domain\ValueObject\MaintenanceEconomicWindow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class MaintenanceEconomicWindowTest
 *
 * Proves calendar-date validation and bounded inclusive report windows without local timezone drift.
 *
 * @category Test
 */
final class MaintenanceEconomicWindowTest extends TestCase
{
  // #region Methods
  /**
   * Method testASingleDayIsAnInclusiveUtcDayWithAnExclusiveFollowingMidnight
   *
   * @access public
   *
   * @return void
   */
  public function testASingleDayIsAnInclusiveUtcDayWithAnExclusiveFollowingMidnight(): void
  {
    $window = MaintenanceEconomicWindow::fromDates('2026-10-25', '2026-10-25');

    self::assertSame('2026-10-25T00:00:00+00:00', $window->from->format('c'));
    self::assertSame('2026-10-26T00:00:00+00:00', $window->to->format('c'));
    self::assertSame('UTC', $window->from->getTimezone()->getName());
    self::assertSame('UTC', $window->to->getTimezone()->getName());
    self::assertSame(86400, $window->to->getTimestamp() - $window->from->getTimestamp());
  }

  /**
   * Method testLeapDayAndYearBoundaryAreRetained
   *
   * @access public
   *
   * @param string $from valid inclusive start
   * @param string $to valid inclusive final day
   * @param string $exclusiveEnd exact following calendar day
   *
   * @return void
   */
  #[DataProvider('validBoundaryCases')]
  public function testLeapDayAndYearBoundaryAreRetained(string $from, string $to, string $exclusiveEnd): void
  {
    $window = MaintenanceEconomicWindow::fromDates($from, $to);

    self::assertSame($from, $window->from->format('Y-m-d'));
    self::assertSame($exclusiveEnd, $window->to->format('Y-m-d'));
  }

  /**
   * Method validBoundaryCases
   *
   * @access public
   *
   * @return iterable<string,array{string,string,string}> calendar boundaries which must remain exact
   */
  public static function validBoundaryCases(): iterable
  {
    yield 'leap day' => ['2024-02-29', '2024-02-29', '2024-03-01'];
    yield 'year boundary' => ['2025-12-31', '2026-01-01', '2026-01-02'];
    yield '366 leap-year days' => ['2024-01-01', '2024-12-31', '2025-01-01'];
    yield '366 days across years' => ['2025-01-01', '2026-01-01', '2026-01-02'];
  }

  /**
   * Method testInvalidNormalizedOrNonCalendarDatesAreRefused
   *
   * @access public
   *
   * @param string $date invalid input which PHP must not normalize silently
   *
   * @return void
   */
  #[DataProvider('invalidDateCases')]
  public function testInvalidNormalizedOrNonCalendarDatesAreRefused(string $date): void
  {
    try {
      MaintenanceEconomicWindow::fromDates($date, $date);
      self::fail('Invalid report date was accepted.');
    } catch (MaintenanceCostException $error) {
      self::assertSame('maintenance_cost_invalid', $error->reason);
      self::assertSame('Report dates must use valid YYYY-MM-DD calendar dates.', $error->getMessage());
    }
  }

  /**
   * Method invalidDateCases
   *
   * @access public
   *
   * @return iterable<string,array{string}> strict invalid calendar values
   */
  public static function invalidDateCases(): iterable
  {
    yield 'non-leap February 29' => ['2025-02-29'];
    yield 'April 31' => ['2026-04-31'];
    yield 'month 13' => ['2026-13-01'];
    yield 'month zero' => ['2026-00-01'];
    yield 'day zero' => ['2026-01-00'];
    yield 'unpadded month' => ['2026-1-01'];
    yield 'unpadded day' => ['2026-01-1'];
    yield 'leading whitespace' => [' 2026-01-01'];
    yield 'timestamp' => ['2026-01-01T00:00:00Z'];
    yield 'empty' => [''];
  }

  /**
   * Method testInvertedAnd367DayWindowsAreRefused
   *
   * @access public
   *
   * @param string $from valid start beyond the bounded range
   * @param string $to valid end beyond the bounded range
   *
   * @return void
   */
  #[DataProvider('invalidRangeCases')]
  public function testInvertedAnd367DayWindowsAreRefused(string $from, string $to): void
  {
    try {
      MaintenanceEconomicWindow::fromDates($from, $to);
      self::fail('Invalid report window was accepted.');
    } catch (MaintenanceCostException $error) {
      self::assertSame('maintenance_cost_invalid', $error->reason);
      self::assertSame('Use an ordered report window of at most 366 calendar days.', $error->getMessage());
    }
  }

  /**
   * Method invalidRangeCases
   *
   * @access public
   *
   * @return iterable<string,array{string,string}> inverted dates and exact oversized windows
   */
  public static function invalidRangeCases(): iterable
  {
    yield 'inverted' => ['2026-10-07', '2026-10-06'];
    yield '367 leap-year days' => ['2024-01-01', '2025-01-01'];
    yield '367 days across years' => ['2025-01-01', '2026-01-02'];
  }
  // #endregion
}
