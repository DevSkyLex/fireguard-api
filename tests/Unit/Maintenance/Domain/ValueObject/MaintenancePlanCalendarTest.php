<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Maintenance\Domain\ValueObject\{MaintenancePlanCalendar, PlanCadence};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;

/**
 * Class MaintenancePlanCalendarTest
 *
 * Verifies anchored fixed calendars and faithful historical sliding state.
 *
 * @category Tests
 */
#[CoversClass(MaintenancePlanCalendar::class)]
final class MaintenancePlanCalendarTest extends TestCase
{
  // #region Methods
  /**
   * Method testFixedStartUsesTheFirstDueDateAsItsOriginalAnchor
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFixedStartUsesTheFirstDueDateAsItsOriginalAnchor(): void
  {
    $cadence = PlanCadence::fromString('P1M');
    $firstDueAt = new DateTimeImmutable('2026-01-31T08:15:00.123456+01:00');

    $calendar = MaintenancePlanCalendar::start($cadence, $firstDueAt);

    self::assertSame($cadence, $calendar->cadence);
    self::assertSame($firstDueAt, $calendar->anchorAt);
    self::assertSame($firstDueAt, $calendar->nextDueAt);
    self::assertFalse($calendar->legacy);
  }

  /**
   * Method testLegacyStartRetainsTheHistoricalCadence
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testLegacyStartRetainsTheHistoricalCadence(): void
  {
    $cadence = PlanCadence::legacyFromString('P1Y6M');
    $firstDueAt = new DateTimeImmutable('2026-01-31T08:15:00+01:00');

    $calendar = MaintenancePlanCalendar::start($cadence, $firstDueAt, true);

    self::assertSame($cadence, $calendar->cadence);
    self::assertSame($firstDueAt, $calendar->anchorAt);
    self::assertSame($firstDueAt, $calendar->nextDueAt);
    self::assertTrue($calendar->legacy);
  }

  /**
   * Method testLegacyStartAllowsUnknownInitialDates
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testLegacyStartAllowsUnknownInitialDates(): void
  {
    $cadence = PlanCadence::legacyFromString('P1M');

    $calendar = MaintenancePlanCalendar::start($cadence, null, true);

    self::assertSame($cadence, $calendar->cadence);
    self::assertNull($calendar->anchorAt);
    self::assertNull($calendar->nextDueAt);
    self::assertTrue($calendar->legacy);
    self::assertSame([], $calendar->preview());
  }

  /**
   * Method testFixedStartRequiresAnExplicitFirstDueDate
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFixedStartRequiresAnExplicitFirstDueDate(): void
  {
    $this->expectException(InvalidValueException::class);

    MaintenancePlanCalendar::start(PlanCadence::fromString('P1M'), null);
  }

  /**
   * Method testInvalidFixedCalendarAndLegacyFlagStatesAreRejected
   *
   * @access public
   *
   * @param bool $cadenceLegacy whether the duration is historical
   * @param ?string $anchorAt the optional original anchor
   * @param ?string $nextDueAt the optional next slot
   * @param bool $legacy the calendar's persisted historical flag
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidCalendarStates')]
  public function testInvalidFixedCalendarAndLegacyFlagStatesAreRejected(bool $cadenceLegacy, ?string $anchorAt, ?string $nextDueAt, bool $legacy): void
  {
    $cadence = $cadenceLegacy ? PlanCadence::legacyFromString('P1M') : PlanCadence::fromString('P1M');
    $this->expectException(InvalidValueException::class);

    new MaintenancePlanCalendar(
      $cadence,
      null === $anchorAt ? null : new DateTimeImmutable($anchorAt),
      null === $nextDueAt ? null : new DateTimeImmutable($nextDueAt),
      $legacy,
    );
  }

  /**
   * Method invalidCalendarStates
   *
   * @access public
   *
   * @return iterable<string, array{bool, ?string, ?string, bool}> rejected flag and fixed calendar combinations
   */
  public static function invalidCalendarStates(): iterable
  {
    $anchor = '2026-01-31T08:15:00.123456+00:00';
    $due = '2026-02-28T08:15:00.123456+00:00';

    yield 'fixed cadence marked legacy' => [false, $anchor, $due, true];
    yield 'historical cadence marked fixed' => [true, $anchor, $due, false];
    yield 'fixed calendar without either date' => [false, null, null, false];
    yield 'fixed calendar without anchor' => [false, null, $due, false];
    yield 'fixed calendar without next due date' => [false, $anchor, null, false];
    yield 'fixed due date before anchor' => [false, $anchor, '2026-01-30T08:15:00.123456+00:00', false];
    yield 'fixed due date between slots' => [false, $anchor, '2026-02-27T08:15:00.123456+00:00', false];
    yield 'fixed due date one microsecond after slot' => [false, $anchor, '2026-02-28T08:15:00.123457+00:00', false];
    yield 'fixed due date one microsecond before slot' => [false, $anchor, '2026-02-28T08:15:00.123455+00:00', false];
  }

  /**
   * Method testTheSameSlotInstantIsAcceptedInAnotherTimezone
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testTheSameSlotInstantIsAcceptedInAnotherTimezone(): void
  {
    $anchor = new DateTimeImmutable('2026-01-31 08:15:00.123456', new DateTimeZone('Europe/Paris'));
    $due = new DateTimeImmutable('2026-02-28T07:15:00.123456+00:00');

    $calendar = new MaintenancePlanCalendar(PlanCadence::fromString('P1M'), $anchor, $due, false);

    self::assertSame($anchor, $calendar->anchorAt);
    self::assertSame($due->format('U.u'), $calendar->nextDueAt?->format('U.u'));
    self::assertSame(['2026-02-28T08:15:00+01:00', '2026-03-31T08:15:00+02:00', '2026-04-30T08:15:00+02:00'], array_map(static fn (DateTimeImmutable $date): string => $date->format('c'), $calendar->preview()));
  }

  /**
   * Method testLegacyDatesRemainIndependentlyNullableAndUnaligned
   *
   * @access public
   *
   * @param ?string $anchorAt the persisted historical anchor
   * @param ?string $nextDueAt the persisted historical due date
   *
   * @return void
   */
  #[Test]
  #[DataProvider('legacyCalendarStates')]
  public function testLegacyDatesRemainIndependentlyNullableAndUnaligned(?string $anchorAt, ?string $nextDueAt): void
  {
    $anchor = null === $anchorAt ? null : new DateTimeImmutable($anchorAt);
    $due = null === $nextDueAt ? null : new DateTimeImmutable($nextDueAt);
    $cadence = PlanCadence::legacyFromString('P1M');

    $calendar = new MaintenancePlanCalendar($cadence, $anchor, $due, true);

    self::assertSame($cadence, $calendar->cadence);
    self::assertSame($anchor, $calendar->anchorAt);
    self::assertSame($due, $calendar->nextDueAt);
    self::assertTrue($calendar->legacy);
    if (null === $due) {
      self::assertSame([], $calendar->preview());
    } else {
      self::assertSame($due->format('U.u'), $calendar->preview(1)[0]->format('U.u'));
    }
  }

  /**
   * Method legacyCalendarStates
   *
   * @access public
   *
   * @return iterable<string, array{?string, ?string}> historical dates that must not be synthesized or realigned
   */
  public static function legacyCalendarStates(): iterable
  {
    yield 'uninitialized dates' => [null, null];
    yield 'anchor without due date' => ['2026-01-31T08:15:00+00:00', null];
    yield 'due date without anchor' => [null, '2026-02-14T08:15:00+00:00'];
    yield 'sliding due date between fixed slots' => ['2026-01-31T08:15:00+00:00', '2026-02-14T08:15:00+00:00'];
    yield 'due date before anchor' => ['2026-01-31T08:15:00+00:00', '2025-12-14T08:15:00+00:00'];
  }

  /**
   * Method testFixedPreviewRestoresTheOriginalDayAfterAShortMonth
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFixedPreviewRestoresTheOriginalDayAfterAShortMonth(): void
  {
    $calendar = new MaintenancePlanCalendar(
      PlanCadence::fromString('P1M'),
      new DateTimeImmutable('2026-01-31T08:15:00.123456+00:00'),
      new DateTimeImmutable('2026-02-28T08:15:00.123456+00:00'),
      false,
    );

    self::assertSame(
      ['2026-02-28T08:15:00.123456+00:00', '2026-03-31T08:15:00.123456+00:00', '2026-04-30T08:15:00.123456+00:00'],
      array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d\TH:i:s.uP'), $calendar->preview()),
    );
  }

  /**
   * Method testFixedAdvanceSkipsMissedSlotsAndPreservesTheOriginalAnchor
   *
   * @access public
   *
   * @param string $validatedAt the validated completion instant
   * @param string $expected the next anchored slot
   *
   * @return void
   */
  #[Test]
  #[DataProvider('fixedAdvanceCases')]
  public function testFixedAdvanceSkipsMissedSlotsAndPreservesTheOriginalAnchor(string $validatedAt, string $expected): void
  {
    $anchor = new DateTimeImmutable('2026-01-31T08:15:00.123456+00:00');
    $due = new DateTimeImmutable('2026-02-28T08:15:00.123456+00:00');
    $cadence = PlanCadence::fromString('P1M');
    $calendar = new MaintenancePlanCalendar($cadence, $anchor, $due, false);

    $advanced = $calendar->advance(new DateTimeImmutable($validatedAt));

    self::assertNotSame($calendar, $advanced);
    self::assertSame($cadence, $advanced->cadence);
    self::assertSame($anchor, $advanced->anchorAt);
    self::assertSame($expected, $advanced->nextDueAt?->format('Y-m-d\TH:i:s.uP'));
    self::assertFalse($advanced->legacy);
    self::assertSame($due, $calendar->nextDueAt);
  }

  /**
   * Method fixedAdvanceCases
   *
   * @access public
   *
   * @return iterable<string, array{string, string}> strict anchored advancement examples
   */
  public static function fixedAdvanceCases(): iterable
  {
    yield 'completion before current due date' => ['2026-02-15T08:15:00+00:00', '2026-03-31T08:15:00.123456+00:00'];
    yield 'completion at current due date' => ['2026-02-28T08:15:00.123456+00:00', '2026-03-31T08:15:00.123456+00:00'];
    yield 'completion after missed slots' => ['2026-06-14T08:15:00+00:00', '2026-06-30T08:15:00.123456+00:00'];
    yield 'completion exactly at a later slot' => ['2026-06-30T08:15:00.123456+00:00', '2026-07-31T08:15:00.123456+00:00'];
  }

  /**
   * Method testFixedYearlyCalendarRestoresLeapDay
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFixedYearlyCalendarRestoresLeapDay(): void
  {
    $calendar = new MaintenancePlanCalendar(
      PlanCadence::fromString('P1Y'),
      new DateTimeImmutable('2024-02-29T08:15:00+00:00'),
      new DateTimeImmutable('2027-02-28T08:15:00+00:00'),
      false,
    );

    self::assertSame(['2027-02-28', '2028-02-29', '2029-02-28'], array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'), $calendar->preview()));
    self::assertSame('2028-02-29', $calendar->advance(new DateTimeImmutable('2027-02-28T08:15:00+00:00'))->nextDueAt?->format('Y-m-d'));
  }

  /**
   * Method testCalendarDaysKeepLocalTimeAcrossDaylightSavingChanges
   *
   * @access public
   *
   * @param string $firstDueAt the local original anchor
   * @param list<string> $expected the calendar dates with expected UTC offsets
   *
   * @return void
   */
  #[Test]
  #[DataProvider('daylightSavingCases')]
  public function testCalendarDaysKeepLocalTimeAcrossDaylightSavingChanges(string $firstDueAt, array $expected): void
  {
    $anchor = new DateTimeImmutable($firstDueAt, new DateTimeZone('Europe/Paris'));
    $calendar = MaintenancePlanCalendar::start(PlanCadence::fromString('P1D'), $anchor);

    self::assertSame($expected, array_map(static fn (DateTimeImmutable $date): string => $date->format('c'), $calendar->preview()));
    $advanced = $calendar->advance($anchor);
    self::assertSame($anchor, $advanced->anchorAt);
    self::assertSame($expected[1], $advanced->nextDueAt?->format('c'));
    self::assertSame('Europe/Paris', $advanced->nextDueAt->getTimezone()->getName());
  }

  /**
   * Method daylightSavingCases
   *
   * @access public
   *
   * @return iterable<string, array{string, list<string>}> spring and autumn calendar transitions
   */
  public static function daylightSavingCases(): iterable
  {
    yield 'spring forward' => ['2026-03-28 08:15:00', ['2026-03-28T08:15:00+01:00', '2026-03-29T08:15:00+02:00', '2026-03-30T08:15:00+02:00']];
    yield 'autumn backward' => ['2026-10-24 08:15:00', ['2026-10-24T08:15:00+02:00', '2026-10-25T08:15:00+01:00', '2026-10-26T08:15:00+01:00']];
  }

  /**
   * Method testLegacyAdvanceSlidesFromCompletionAndPreservesTheStoredAnchor
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testLegacyAdvanceSlidesFromCompletionAndPreservesTheStoredAnchor(): void
  {
    $cadence = PlanCadence::legacyFromString('P1M');
    $anchor = new DateTimeImmutable('2025-11-30T08:15:00+00:00');
    $due = new DateTimeImmutable('2026-02-14T08:15:00+00:00');
    $calendar = new MaintenancePlanCalendar($cadence, $anchor, $due, true);

    $advanced = $calendar->advance(new DateTimeImmutable('2026-01-31T08:15:00+00:00'));

    self::assertSame($cadence, $advanced->cadence);
    self::assertSame($anchor, $advanced->anchorAt);
    self::assertSame('2026-03-03T08:15:00+00:00', $advanced->nextDueAt?->format('c'));
    self::assertTrue($advanced->legacy);
    self::assertSame($due, $calendar->nextDueAt);
    self::assertSame(['2026-03-03', '2026-04-03', '2026-05-03'], array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'), $advanced->preview()));
  }

  /**
   * Method testLegacyAdvanceCanInitializeDueDateWithoutSynthesizingAnAnchor
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testLegacyAdvanceCanInitializeDueDateWithoutSynthesizingAnAnchor(): void
  {
    $calendar = new MaintenancePlanCalendar(PlanCadence::legacyFromString('P1Y6M'), null, null, true);

    $advanced = $calendar->advance(new DateTimeImmutable('2026-01-31T08:15:00+00:00'));

    self::assertNull($advanced->anchorAt);
    self::assertSame('2027-07-31T08:15:00+00:00', $advanced->nextDueAt?->format('c'));
    self::assertTrue($advanced->legacy);
    self::assertNull($calendar->nextDueAt);
  }

  /**
   * Method testLegacyPreviewRetainsHistoricalMonthOverflow
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testLegacyPreviewRetainsHistoricalMonthOverflow(): void
  {
    $calendar = new MaintenancePlanCalendar(
      PlanCadence::legacyFromString('P1M'),
      null,
      new DateTimeImmutable('2026-01-31T08:15:00+00:00'),
      true,
    );

    self::assertSame(['2026-01-31', '2026-03-03', '2026-04-03'], array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'), $calendar->preview()));
  }

  /**
   * Method testPreviewCountRemainsBounded
   *
   * @access public
   *
   * @param int $count the invalid requested preview size
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidPreviewCounts')]
  public function testPreviewCountRemainsBounded(int $count): void
  {
    $calendar = MaintenancePlanCalendar::start(PlanCadence::fromString('P1D'), new DateTimeImmutable('2026-01-01T08:15:00+00:00'));
    $this->expectException(InvalidValueException::class);

    $calendar->preview($count);
  }

  /**
   * Method invalidPreviewCounts
   *
   * @access public
   *
   * @return iterable<string, array{int}> rejected preview sizes
   */
  public static function invalidPreviewCounts(): iterable
  {
    yield 'empty preview' => [0];
    yield 'excessive preview' => [101];
  }
  // #endregion
}
