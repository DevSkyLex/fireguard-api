<?php

declare(strict_types=1);

namespace Workload\Domain\ValueObject;

use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Value object LocalDate, independent of UTC offsets and daylight saving.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LocalDate
{
  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param string $value candidate value to validate without silently coercing it
   */
  private function __construct(public string $value)
  {
  }

  /**
   * Parses a valid local calendar date without timezone conversion.
   *
   * @since 1.0.0
   *
   * @param string $value candidate value to validate without silently coercing it
   *
   * @return self validated organization-local calendar date
   */
  public static function fromString(string $value): self
  {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (false === $date || $date->format('Y-m-d') !== $value) {
      throw InvalidValueException::because('Expected a valid local date (YYYY-MM-DD).');
    }

    return new self($value);
  }

  /**
   * Advances to the next calendar date.
   *
   * @since 1.0.0
   *
   * @return self following calendar date
   */
  public function next(): self
  {
    return self::fromString(new DateTimeImmutable($this->value)->modify('+1 day')->format('Y-m-d'));
  }

  /**
   * @since 1.0.0
   *
   * @return int ISO weekday, Monday = 1
   */
  public function weekday(): int
  {
    return (int) new DateTimeImmutable($this->value)->format('N');
  }
  // #endregion
}
