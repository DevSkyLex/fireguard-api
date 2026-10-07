<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\DecimalAmount;

/**
 * Class DecimalAmountTest.
 *
 * Verifies exact valuation, proportional allocation and signed rounding.
 *
 * @category UnitTest
 */
#[CoversClass(DecimalAmount::class)]
final class DecimalAmountTest extends TestCase
{
  /**
   * Method testArithmeticRetainsSmallAmountsBeyondFloatPrecision.
   */
  public function testArithmeticRetainsSmallAmountsBeyondFloatPrecision(): void
  {
    $amount = DecimalAmount::fromString('999999999999999999.000001');
    self::assertSame('999999999999999999.000002', $amount->add(DecimalAmount::fromString('0.000001'))->toString());
    self::assertSame('0.000001', $amount->subtract(DecimalAmount::fromString('999999999999999999'))->toString());
    self::assertSame('0.000000', DecimalAmount::fromString('-0.000000')->toString());
  }

  /**
   * Method testSignedRoundingIsSymmetric.
   *
   * @param string $input signed numerator
   * @param string $expected rounded quotient
   */
  #[DataProvider('roundingCases')]
  public function testSignedRoundingIsSymmetric(string $input, string $expected): void
  {
    self::assertSame($expected, DecimalAmount::fromString($input)->divideInt(2)->value());
  }

  /**
   * Method roundingCases.
   *
   * @return iterable<string,array{string,string}> positive and negative midpoint boundaries
   */
  public static function roundingCases(): iterable
  {
    yield 'positive midpoint' => ['0.000001', '0.000001'];
    yield 'negative midpoint' => ['-0.000001', '-0.000001'];
    yield 'positive below midpoint' => ['0.000004', '0.000002'];
    yield 'negative below midpoint' => ['-0.000004', '-0.000002'];
  }

  /**
   * Method testAllocationRoundsOnlyTheFinalProportionalValue.
   */
  public function testAllocationRoundsOnlyTheFinalProportionalValue(): void
  {
    $total = DecimalAmount::fromString('10');
    self::assertSame('10.000000', $total->multiplyAndDivide(DecimalAmount::fromInt(3), DecimalAmount::fromInt(3))->toString());
    self::assertSame('3.333333', $total->multiplyAndDivide(DecimalAmount::fromInt(1), DecimalAmount::fromInt(3))->toString());
    self::assertSame('1.000000', DecimalAmount::fromString('60.000001')->multiplyInt(1)->divideInt(60)->toString());
    self::assertSame('-0.000001', DecimalAmount::fromString('-0.000001')->multiply(DecimalAmount::fromString('0.5'))->toString());
  }

  /**
   * Method testIndividualizationRequiresAnExactBoundedInteger.
   */
  public function testIndividualizationRequiresAnExactBoundedInteger(): void
  {
    self::assertSame(100, DecimalAmount::fromString('100.000000')->toInt());
    self::assertFalse(DecimalAmount::fromString('0.5')->isInteger());
    $this->expectException(InvalidValueException::class);
    DecimalAmount::fromString('0.5')->toInt();
  }

  /**
   * Method testZeroDivisorsAreRejected.
   */
  public function testZeroDivisorsAreRejected(): void
  {
    $this->expectException(InvalidValueException::class);
    DecimalAmount::fromInt(1)->multiplyAndDivide(DecimalAmount::fromInt(1), DecimalAmount::zero());
  }

  /**
   * Method testInvalidTransportValuesAreRejected.
   *
   * @param string $value malformed or excessive precision
   */
  #[DataProvider('invalidValues')]
  public function testInvalidTransportValuesAreRejected(string $value): void
  {
    $this->expectException(InvalidValueException::class);
    DecimalAmount::fromString($value);
  }

  /**
   * Method invalidValues.
   *
   * @return iterable<string,array{string}> values which must never silently coerce
   */
  public static function invalidValues(): iterable
  {
    foreach (['1e3', '1,5', 'NaN', ' 1', '1 ', '+1', '0.0000001', '1.', '.5', '1000000000000000000'] as $value) {
      yield $value => [$value];
    }
  }
}
