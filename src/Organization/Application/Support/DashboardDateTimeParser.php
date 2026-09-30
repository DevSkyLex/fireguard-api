<?php

declare(strict_types=1);

namespace Organization\Application\Support;

use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;

use function preg_match;
use function sprintf;
use function str_pad;
use function substr;

/**
 * Class DashboardDateTimeParser
 *
 * Parses dashboard date filters that include an explicit ISO 8601 timezone offset.
 *
 * @category Support
 */
final class DashboardDateTimeParser
{
  // #region Methods
  /**
   * Method parseNullable
   *
   * Returns null for an absent filter and parses any non-empty value as a date-time.
   * InvalidValueException reports a non-empty value outside the accepted ISO 8601 format.
   *
   * @access public
   *
   * @param string|null $value optional date-time filter value
   * @param string $filterName filter name used in validation errors
   *
   * @return DateTimeImmutable|null parsed date-time, or null when the filter is absent
   */
  public static function parseNullable(?string $value, string $filterName): ?DateTimeImmutable
  {
    if (null === $value || '' === $value) {
      return null;
    }

    return self::parse($value, $filterName);
  }

  /**
   * Method parse
   *
   * Parses an ISO 8601 date-time with an explicit timezone and optional fractional seconds.
   * Fractional seconds may contain one to six digits.
   * InvalidValueException reports malformed values or a missing explicit timezone.
   *
   * @access public
   *
   * @param string $value date-time string with a timezone offset
   * @param string $filterName filter name used in validation errors
   *
   * @return DateTimeImmutable parsed date-time with the supplied offset
   */
  public static function parse(string $value, string $filterName): DateTimeImmutable
  {
    if (!preg_match(
      '/^(?<date>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?<fraction>\.\d{1,6})?(?<timezone>Z|[+-]\d{2}:\d{2})$/',
      $value,
      $matches,
    )) {
      throw InvalidValueException::because(sprintf(
        'Invalid "%s" datetime filter. Use an ISO 8601 datetime with an explicit timezone offset.',
        $filterName,
      ));
    }

    $fraction = $matches['fraction'];
    if ('' !== $fraction) {
      $fraction = '.' . str_pad(substr($fraction, 1), 6, '0');
    }

    $timeZone = $matches['timezone'];
    $normalizedValue = $matches['date'] . $fraction . ('Z' === $timeZone ? '+00:00' : $timeZone);
    $format = '' === $fraction ? '!Y-m-d\TH:i:sP' : '!Y-m-d\TH:i:s.uP';
    $dateTime = DateTimeImmutable::createFromFormat($format, $normalizedValue);

    $errors = DateTimeImmutable::getLastErrors();
    if (
      false === $dateTime
      || (false !== $errors && (0 !== $errors['warning_count'] || 0 !== $errors['error_count']))
    ) {
      throw InvalidValueException::because(sprintf(
        'Invalid "%s" datetime filter. Use an ISO 8601 datetime with an explicit timezone offset.',
        $filterName,
      ));
    }

    return $dateTime;
  }
  // #endregion
}
