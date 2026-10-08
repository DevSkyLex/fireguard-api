<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\ValueObject;

use DateTimeImmutable;
use Maintenance\Domain\ValueObject\MaintenanceControlDates;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class MaintenanceControlDatesTest
 *
 * Verifies control-date precedence independently of storage paging order.
 *
 * @category Tests
 */
final class MaintenanceControlDatesTest extends TestCase
{
  // #region Methods
  /**
   * Method earliestDueAndLatestCompletionSurviveAccumulation
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function earliestDueAndLatestCompletionSurviveAccumulation(): void
  {
    $early = new DateTimeImmutable('2026-11-01');
    $late = new DateTimeImmutable('2026-12-01');
    $completed = new DateTimeImmutable('2026-10-01');
    $empty = MaintenanceControlDates::empty();
    $dates = $empty->including($late, new DateTimeImmutable('2026-09-01'))->including($early, $completed);

    self::assertFalse($empty->tracked);
    self::assertTrue($dates->tracked);
    self::assertSame($early, $dates->nextDueAt);
    self::assertSame($completed, $dates->lastCompletedAt);
  }

  /**
   * Method missingDueDateDominatesEveryPageOrder
   *
   * @access public
   *
   * @param bool $missingFirst whether the missing due date arrives before a scheduled control
   *
   * @return void
   */
  #[Test]
  #[DataProvider('pageOrders')]
  public function missingDueDateDominatesEveryPageOrder(bool $missingFirst): void
  {
    $due = new DateTimeImmutable('2026-11-01');
    $completed = new DateTimeImmutable('2026-10-01');
    $dates = MaintenanceControlDates::empty();
    $dates = $missingFirst ? $dates->including(null, $completed)->including($due, null) : $dates->including($due, null)->including(null, $completed);

    self::assertTrue($dates->tracked);
    self::assertNull($dates->nextDueAt);
    self::assertSame($completed, $dates->lastCompletedAt);
  }

  /**
   * Method pageOrders
   *
   * @access public
   *
   * @return iterable<string, array{bool}> both source orders
   */
  public static function pageOrders(): iterable
  {
    yield 'unscheduled first' => [true];
    yield 'unscheduled last' => [false];
  }
  // #endregion
}
