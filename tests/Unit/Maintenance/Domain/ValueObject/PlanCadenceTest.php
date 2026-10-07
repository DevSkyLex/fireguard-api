<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Maintenance\Domain\ValueObject\PlanCadence;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;

/**
 * Class PlanCadenceTest
 *
 * Verifies fixed calendar anchors and historical sliding arithmetic.
 *
 * @category Tests
 */
#[CoversClass(PlanCadence::class)]
final class PlanCadenceTest extends TestCase
{
  // #region Methods
  /**
   * Method testFixedCadencesPreserveCalendarAnchor
   *
   * @access public
   *
   * @param string $interval the calendar duration
   * @param string $anchor the original due date
   * @param int $index the recurrence index
   * @param string $expected the expected due date
   *
   * @return void
   */
  #[Test]
  #[DataProvider('fixedCalendarCases')]
  public function testFixedCadencesPreserveCalendarAnchor(string $interval, string $anchor, int $index, string $expected): void
  {
    $cadence = PlanCadence::fromString($interval);
    $date = $cadence->dateAt(new DateTimeImmutable($anchor . 'T08:30:00+00:00'), $index);

    self::assertSame($expected . 'T08:30:00+00:00', $date->format('c'));
    self::assertFalse($cadence->legacy);
    self::assertSame($interval, $cadence->value);
  }

  /**
   * Method fixedCalendarCases
   *
   * @access public
   *
   * @return iterable<string, array{string, string, int, string}> calendar examples
   */
  public static function fixedCalendarCases(): iterable
  {
    yield 'monthly February clamp' => ['P1M', '2026-01-31', 1, '2026-02-28'];
    yield 'monthly original day restored' => ['P1M', '2026-01-31', 2, '2026-03-31'];
    yield 'monthly April clamp' => ['P1M', '2026-01-31', 3, '2026-04-30'];
    yield 'two month year boundary' => ['P2M', '2026-11-30', 2, '2027-03-30'];
    yield 'annual leap day clamp' => ['P1Y', '2024-02-29', 1, '2025-02-28'];
    yield 'annual leap day restored' => ['P1Y', '2024-02-29', 4, '2028-02-29'];
    yield 'daily leap year' => ['P1D', '2024-02-28', 2, '2024-03-01'];
    yield 'weekly' => ['P2W', '2026-01-01', 2, '2026-01-29'];
    yield 'index zero' => ['P1M', '2026-01-31', 0, '2026-01-31'];
  }

  /**
   * Method testInvalidCadencesAreRejected
   *
   * @access public
   *
   * @param string $interval the malformed or out-of-bounds interval
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidCadences')]
  public function testInvalidCadencesAreRejected(string $interval): void
  {
    $this->expectException(InvalidValueException::class);

    PlanCadence::fromString($interval);
  }

  /**
   * Method invalidCadences
   *
   * @access public
   *
   * @return iterable<string, array{string}> rejected durations
   */
  public static function invalidCadences(): iterable
  {
    foreach (['', 'P0D', 'P01D', 'P-1D', 'P1Y6M', 'PT1H', 'P1.5D', 'P3651D', 'P521W', 'P121M', 'P11Y', 'P1D\n', ' P1D'] as $value) {
      yield $value => [$value];
    }
  }

  /**
   * Method testPreviewUsesOriginalAnchorAfterFebruary
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPreviewUsesOriginalAnchorAfterFebruary(): void
  {
    $dates = PlanCadence::fromString('P1M')->preview(
      new DateTimeImmutable('2026-01-31T00:00:00+00:00'),
      new DateTimeImmutable('2026-02-28T00:00:00+00:00'),
    );

    self::assertSame(['2026-02-28', '2026-03-31', '2026-04-30'], array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'), $dates));
  }

  /**
   * Method testNextAfterSkipsMissedDatesAndIsStrict
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testNextAfterSkipsMissedDatesAndIsStrict(): void
  {
    $cadence = PlanCadence::fromString('P1M');
    $anchor = new DateTimeImmutable('2026-01-31T08:00:00+00:00');

    self::assertSame('2026-06-30', $cadence->nextAfter($anchor, new DateTimeImmutable('2026-06-14T08:00:00+00:00'))->format('Y-m-d'));
    self::assertSame('2026-07-31', $cadence->nextAfter($anchor, new DateTimeImmutable('2026-06-30T08:00:00+00:00'))->format('Y-m-d'));
    self::assertEquals($anchor, $cadence->nextAfter($anchor, new DateTimeImmutable('2025-12-31T08:00:00+00:00')));
  }

  /**
   * Method testCalendarDaysKeepLocalTimeAcrossDaylightSaving
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCalendarDaysKeepLocalTimeAcrossDaylightSaving(): void
  {
    $anchor = new DateTimeImmutable('2026-03-28 08:00:00', new DateTimeZone('Europe/Paris'));
    $cadence = PlanCadence::fromString('P1D');

    self::assertSame('2026-03-29T08:00:00+02:00', $cadence->addTo($anchor)->format('c'));
    self::assertSame('2026-04-03T08:00:00+02:00', $cadence->nextAfter($anchor, new DateTimeImmutable('2026-04-02T10:00:00+00:00'))->format('c'));
  }

  /**
   * Method testLegacyCompositeAndOverflowBehaviorAreRetained
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testLegacyCompositeAndOverflowBehaviorAreRetained(): void
  {
    $anchor = new DateTimeImmutable('2026-01-31T00:00:00+00:00');
    $cadence = PlanCadence::legacyFromString('P1M');

    self::assertTrue($cadence->legacy);
    self::assertSame('2026-03-03', $cadence->addTo($anchor)->format('Y-m-d'));
    self::assertSame('2026-04-03', $cadence->dateAt($anchor, 2)->format('Y-m-d'));
    self::assertSame('2027-07-31', PlanCadence::legacyFromString('P1Y6M')->addTo($anchor)->format('Y-m-d'));
    self::assertSame('2026-06-14', $cadence->nextAfter($anchor, new DateTimeImmutable('2026-05-14T00:00:00+00:00'))->format('Y-m-d'));
  }

  /**
   * Method testLegacyBoundsRemainHistorical
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testLegacyBoundsRemainHistorical(): void
  {
    $this->expectException(InvalidValueException::class);

    PlanCadence::legacyFromString('P1D');
  }

  /**
   * Method testPreviewIsBounded
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPreviewIsBounded(): void
  {
    $this->expectException(InvalidValueException::class);

    PlanCadence::fromString('P1D')->preview(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-01'), 101);
  }

  /**
   * Method testNegativeIndexesAreRejected
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testNegativeIndexesAreRejected(): void
  {
    $this->expectException(InvalidValueException::class);

    PlanCadence::fromString('P1D')->dateAt(new DateTimeImmutable('2026-01-01'), -1);
  }
  // #endregion
}
