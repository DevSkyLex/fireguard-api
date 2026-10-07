<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Domain\Service;

use MaintenanceCost\Domain\Service\MaintenanceCostCalculator;
use PHPUnit\Framework\TestCase;

/** Class MaintenanceCostCalculatorTest. Exact rounding and incomplete totals. @category Test */
final class MaintenanceCostCalculatorTest extends TestCase
{
  public function testComputedHourlyCostMayExceedIndividualInputPrecisionWithoutLosingItsExactTotal(): void
  {
    $calculator = new MaintenanceCostCalculator();
    $amount = $calculator->timeAmount('900000000000000000.000000', 120);
    self::assertSame('1800000000000000000.000000', $amount);
    self::assertSame(['total' => '1800000000000000000.000001', 'knownTotal' => '1800000000000000000.000001', 'complete' => true], $calculator->total([$amount, '0.000001']));
    self::assertSame(['total' => null, 'knownTotal' => $amount, 'complete' => false], $calculator->total([$amount, null]));
  }

  public function testHourlyRateIsMultipliedByAllMinutesBeforeOneDivision(): void
  {
    $calculator = new MaintenanceCostCalculator();
    self::assertSame('0.000580', $calculator->timeAmount('0.000029', 1200));
    self::assertSame('59.000001', $calculator->timeAmount('60.000001', 59));
    self::assertSame('1.666667', $calculator->timeAmount('100.000001', 1));
  }

  public function testUnknownAmountDoesNotBecomeZeroOrACompleteSubtotal(): void
  {
    self::assertSame(['total' => null, 'knownTotal' => '0.300000', 'complete' => false], new MaintenanceCostCalculator()->total(['0.100000', null, '0.200000']));
  }

  public function testSignedCorrectionsAndLargeValuesKeepExactPrecision(): void
  {
    self::assertSame(['total' => '99999999999999999.000001', 'knownTotal' => '99999999999999999.000001', 'complete' => true], new MaintenanceCostCalculator()->total(['99999999999999999.100001', '-0.100000']));
  }

  public function testAnEmptyCostContainsAKnownZero(): void
  {
    self::assertSame(['total' => '0.000000', 'knownTotal' => '0.000000', 'complete' => true], new MaintenanceCostCalculator()->total([]));
  }
}
