<?php

declare(strict_types=1);

namespace Maintenance\Domain\ValueObject;

use DateInterval;
use DateTimeImmutable;
use Exception;
use Shared\Domain\Exception\InvalidValueException;

use function sprintf;

/**
 * ValueObject PeriodicityInterval.
 *
 * An inspection periodicity expressed as an ISO-8601 duration string
 * (e.g. `P90D`, `P1Y`), validated to span between one month and ten years.
 * Mirrors the bounds enforced by
 * {@see \Organization\Domain\ValueObject\OrganizationComplianceSettings::assertPeriodicityInBounds()}
 * so an override set on a maintenance schedule is held to the same
 * invariant as the organization-wide compliance policy it can supersede.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PeriodicityInterval
{
  // #region Constants
  /**
   * Inclusive lower bound: 28 days (roughly one month).
   */
  private const string MIN_DURATION = 'P28D';

  /**
   * Inclusive upper bound: ten years.
   */
  private const string MAX_DURATION = 'P10Y';

  /**
   * Fixed reference date used to compare two ISO-8601 durations, mirroring
   * the organization compliance settings' bounds check.
   */
  private const string REFERENCE_DATE = '2000-01-01T00:00:00+00:00';
  // #endregion

  // #region Properties
  /**
   * Property value.
   *
   * The validated ISO-8601 duration string.
   *
   * @since 1.0.0
   */
  public string $value;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the PeriodicityInterval class. Use
   * {@see self::fromString()} instead of instantiating directly.
   *
   * @since 1.0.0
   *
   * @param string $value the ISO-8601 duration string, already validated
   */
  private function __construct(string $value)
  {
    $this->value = $value;
  }
  // #endregion

  // #region Methods
  /**
   * Method fromString.
   *
   * @static
   *
   * Validates and creates a periodicity interval from an ISO-8601 duration
   * string.
   *
   * @since 1.0.0
   *
   * @param string $value the ISO-8601 duration string
   *
   * @return self the validated periodicity interval
   */
  public static function fromString(string $value): self
  {
    $interval = self::parse($value);
    $reference = new DateTimeImmutable(self::REFERENCE_DATE);
    $target = $reference->add($interval);

    if ($target < $reference->add(new DateInterval(self::MIN_DURATION))) {
      throw InvalidValueException::because(sprintf('Periodicity "%s" must be at least one month.', $value));
    }

    if ($target > $reference->add(new DateInterval(self::MAX_DURATION))) {
      throw InvalidValueException::because(sprintf('Periodicity "%s" must not exceed ten years.', $value));
    }

    return new self($value);
  }

  /**
   * Method addTo.
   *
   * Adds this periodicity to a reference date, returning the resulting due
   * date.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $date the reference date (e.g. the last inspection closure)
   *
   * @return DateTimeImmutable the resulting due date
   */
  public function addTo(DateTimeImmutable $date): DateTimeImmutable
  {
    return $date->add(self::parse($this->value));
  }

  /**
   * Method parse.
   *
   * @static
   *
   * Parses an ISO-8601 duration string, translating a malformed value into
   * a domain exception.
   *
   * @since 1.0.0
   *
   * @param string $value the ISO-8601 duration string
   *
   * @return DateInterval the parsed interval
   */
  private static function parse(string $value): DateInterval
  {
    try {
      return new DateInterval($value);
    } catch (Exception $exception) {
      throw new InvalidValueException(sprintf('"%s" is not a valid ISO-8601 duration.', $value), 0, $exception);
    }
  }
  // #endregion
}
