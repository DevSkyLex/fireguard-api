<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function bcadd;
use function bccomp;
use function bcdiv;
use function bcmul;
use function bcsub;
use function explode;
use function is_numeric;
use function preg_match;
use function str_starts_with;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * ValueObject DecimalAmount.
 *
 * Exact decimal arithmetic for internal quantities and costs. Values are strings,
 * never binary floats. Multiplication and division round half away from zero at
 * six decimal places; inventory retains the residual value in its balance.
 *
 * @category ValueObject
 */
final readonly class DecimalAmount
{
  public const int SCALE = 6;

  /**
   * Constructor.
   *
   * @param numeric-string $value normalized decimal with exactly six fractional digits
   */
  private function __construct(private string $value)
  {
  }

  /**
   * Method fromString.
   *
   * @param string $value signed canonical input, at most eighteen integer and six fractional digits
   *
   * @return self exact normalized decimal
   */
  public static function fromString(string $value): self
  {
    if (1 !== preg_match('/^-?\d{1,18}(?:\.\d{1,6})?$/D', $value) || !is_numeric($value)) {
      throw new InvalidValueException('Use a decimal string with at most eighteen integer and six fractional digits.');
    }

    return new self(bcadd($value, '0', self::SCALE));
  }

  /**
   * Method fromInt.
   *
   * @param int $value integer quantity or amount
   *
   * @return self exact integer decimal
   */
  public static function fromInt(int $value): self
  {
    return new self(bcadd((string) $value, '0', self::SCALE));
  }

  /**
   * Method zero.
   *
   * @return self explicitly known zero, never an unknown valuation
   */
  public static function zero(): self
  {
    return new self('0.000000');
  }

  /**
   * Method add.
   *
   * @param self $other exact operand
   *
   * @return self exact sum
   */
  public function add(self $other): self
  {
    return new self(bcadd($this->value, $other->value, self::SCALE));
  }

  /**
   * Method subtract.
   *
   * @param self $other exact operand
   *
   * @return self exact difference
   */
  public function subtract(self $other): self
  {
    return new self(bcsub($this->value, $other->value, self::SCALE));
  }

  /**
   * Method multiply.
   *
   * @param self $other exact operand
   *
   * @return self product rounded once to the internal precision
   */
  public function multiply(self $other): self
  {
    return self::rounded(bcmul($this->value, $other->value, self::SCALE + 1));
  }

  /**
   * Method divide.
   *
   * @param self $other nonzero exact divisor
   *
   * @return self quotient rounded once to the internal precision
   */
  public function divide(self $other): self
  {
    if ($other->isZero()) {
      throw new InvalidValueException('A decimal divisor must be nonzero.');
    }

    return self::rounded(bcdiv($this->value, $other->value, self::SCALE + 1));
  }

  /**
   * Method multiplyAndDivide.
   *
   * @param self $multiplier exact quantity to allocate
   * @param self $divisor nonzero total quantity
   *
   * @return self proportional value rounded once, without an intermediate rounded average
   */
  public function multiplyAndDivide(self $multiplier, self $divisor): self
  {
    if ($divisor->isZero()) {
      throw new InvalidValueException('A decimal divisor must be nonzero.');
    }

    $product = bcmul($this->value, $multiplier->value, self::SCALE * 2);

    return self::rounded(bcdiv($product, $divisor->value, self::SCALE + 1));
  }

  /**
   * Method multiplyInt.
   *
   * @param int $quantity integer multiplier
   *
   * @return self exact product
   */
  public function multiplyInt(int $quantity): self
  {
    return new self(bcmul($this->value, (string) $quantity, self::SCALE));
  }

  /**
   * Method divideInt.
   *
   * @param int $divisor nonzero integer divisor
   *
   * @return self rounded quotient
   */
  public function divideInt(int $divisor): self
  {
    return $this->divide(self::fromInt($divisor));
  }

  /**
   * Method compareTo.
   *
   * @param self $other exact operand
   *
   * @return int negative, zero or positive comparison
   */
  public function compareTo(self $other): int
  {
    return bccomp($this->value, $other->value, self::SCALE);
  }

  /**
   * Method isZero.
   *
   * @return bool whether the value is exactly zero
   */
  public function isZero(): bool
  {
    return 0 === bccomp($this->value, '0', self::SCALE);
  }

  /**
   * Method isNegative.
   *
   * @return bool whether the value is strictly negative
   */
  public function isNegative(): bool
  {
    return bccomp($this->value, '0', self::SCALE) < 0;
  }

  /**
   * Method isInteger.
   *
   * @return bool whether no fractional quantity remains
   */
  public function isInteger(): bool
  {
    return 0 === bccomp($this->value, bcadd($this->value, '0', 0), self::SCALE);
  }

  /**
   * Method toInt.
   *
   * @return int exact integer for bounded equipment individualization
   */
  public function toInt(): int
  {
    if (!$this->isInteger() || bccomp($this->value, (string) PHP_INT_MAX, self::SCALE) > 0 || bccomp($this->value, (string) PHP_INT_MIN, self::SCALE) < 0) {
      throw new InvalidValueException('The decimal must be an integer within the platform integer range.');
    }

    return (int) bcadd($this->value, '0', 0);
  }

  /**
   * Method toString.
   *
   * @return numeric-string exact transport and storage representation
   */
  public function toString(): string
  {
    return $this->value;
  }

  /**
   * Method value.
   *
   * @return numeric-string exact transport and storage representation
   */
  public function value(): string
  {
    return $this->value;
  }

  /**
   * Method rounded.
   *
   * @param numeric-string $value arithmetic result retaining the seventh fractional digit
   *
   * @return self signed half-up result; zero has no negative sign
   */
  private static function rounded(string $value): self
  {
    $truncated = bcadd($value, '0', self::SCALE);
    $fraction = explode('.', $value, 2)[1] ?? '';
    if ((int) ($fraction[self::SCALE] ?? '0') >= 5) {
      $truncated = bcadd($truncated, str_starts_with($value, '-') ? '-0.000001' : '0.000001', self::SCALE);
    }

    return new self($truncated);
  }
}
