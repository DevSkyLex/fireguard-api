<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\DateRange;

/**
 * Test DateRangeTest.
 *
 * @category Unit Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Domain\ValueObject\DateRange
 */
#[CoversClass(className: DateRange::class)]
final class DateRangeTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreatedWithValidDates.
   *
   * Tests that a valid DateRange can be created
   * with start and end dates.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testCanBeCreatedWithValidDates(): void
  {
    $start = new DateTimeImmutable(datetime: '2023-01-01');
    $end = new DateTimeImmutable(datetime: '2023-01-31');
    $range = new DateRange(start: $start, end: $end);

    $this->assertEquals(expected: $start, actual: $range->start);
    $this->assertEquals(expected: $end, actual: $range->end);
  }

  /**
   * Method testCannotBeCreatedWithStartAfterEnd.
   *
   * Tests that creating a DateRange where start date
   * is after end date throws an
   * InvalidValueException.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testCannotBeCreatedWithStartAfterEnd(): void
  {
    $start = new DateTimeImmutable(datetime: '2023-02-01');
    $end = new DateTimeImmutable(datetime: '2023-01-31');

    $this->expectException(exception: InvalidValueException::class);
    new DateRange(start: $start, end: $end);
  }

  /**
   * Method testContains.
   *
   * Tests the contains method to verify if a
   * date is within the range.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testContains(): void
  {
    $start = new DateTimeImmutable(datetime: '2023-01-01');
    $end = new DateTimeImmutable(datetime: '2023-01-31');
    $range = new DateRange(start: $start, end: $end);

    $this->assertTrue(condition: $range->contains(date: new DateTimeImmutable(datetime: '2023-01-15')));
    $this->assertTrue(condition: $range->contains(date: $start));
    $this->assertTrue(condition: $range->contains(date: $end));
    $this->assertFalse(condition: $range->contains(date: new DateTimeImmutable(datetime: '2022-12-31')));
    $this->assertFalse(condition: $range->contains(date: new DateTimeImmutable(datetime: '2023-02-01')));
  }

  /**
   * Method testEquality.
   *
   * Tests equality comparison between DateRange objects.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testEquality(): void
  {
    $start = new DateTimeImmutable(datetime: '2023-01-01');
    $end = new DateTimeImmutable(datetime: '2023-01-31');
    $r1 = new DateRange(start: $start, end: $end);
    $r2 = new DateRange(start: $start, end: $end);
    $r3 = new DateRange(start: $start, end: new DateTimeImmutable(datetime: '2023-02-01'));

    $this->assertTrue(condition: $r1->equals($r2));
    $this->assertFalse(condition: $r1->equals($r3));
  }

  // #endregion
}
